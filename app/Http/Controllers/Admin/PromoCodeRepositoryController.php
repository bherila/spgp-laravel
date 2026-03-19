<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PromoCodeRepository;
use App\Models\Season;
use Carbon\Carbon;
use Illuminate\Http\Request;

class PromoCodeRepositoryController extends Controller
{
    /**
     * Display the promo code repository page for a season.
     */
    public function index(int $seasonId)
    {
        $season = Season::findOrFail($seasonId);
        return view('admin.promo-code-repository', ['season' => $season]);
    }

    /**
     * List all promo codes for a season.
     */
    public function list(int $seasonId)
    {
        $season = Season::findOrFail($seasonId);

        $codes = PromoCodeRepository::where('season_id', $seasonId)
            ->orderBy('start_date', 'desc')
            ->orderBy('promo_code')
            ->get();

        return response()->json([
            'season' => $season,
            'promo_codes' => $codes,
        ]);
    }

    /**
     * Import promo codes from TSV paste.
     * Expected TSV columns: Code, Date Added
     * Country is provided as a request parameter.
     */
    public function import(Request $request, int $seasonId)
    {
        Season::findOrFail($seasonId);

        $validated = $request->validate([
            'tsv' => ['required', 'string'],
            'country' => ['required', 'string', 'in:USA,Canada'],
        ]);

        $country = $validated['country'];
        $imported = 0;
        $skipped = 0;
        $errors = [];

        // Split the TSV into lines, strip trailing \r from Windows line endings
        $rawLines = explode("\n", str_replace("\r", '', $validated['tsv']));

        foreach ($rawLines as $lineIndex => $rawLine) {
            $rawLine = trim($rawLine);
            if ($rawLine === '') {
                continue;
            }

            // Use str_getcsv with tab separator for proper CSV/TSV parsing
            $parts = str_getcsv($rawLine, "\t");

            if (count($parts) < 2) {
                $errors[] = "Line " . ($lineIndex + 1) . ": expected 2 columns (Code, Date Added)";
                continue;
            }

            $promoCode = trim($parts[0]);
            $startDateRaw = trim($parts[1]);

            // Skip header row if present (first non-empty line containing "Code" or "Date")
            if ($imported === 0 && $skipped === 0 && empty($errors) &&
                (stripos($promoCode, 'code') !== false || stripos($startDateRaw, 'date') !== false)) {
                continue;
            }

            if (empty($promoCode)) {
                continue;
            }

            // Parse start date
            try {
                $startDate = Carbon::parse($startDateRaw);
            } catch (\Exception $e) {
                $errors[] = "Line " . ($lineIndex + 1) . ": invalid date '{$startDateRaw}'";
                continue;
            }

            // Expiration date is September 1st following the start date
            $expirationDate = Carbon::create($startDate->year, 9, 1);
            if ($startDate->month >= 9) {
                $expirationDate->addYear();
            }

            // Create the promo code in the repository if it doesn't already exist
            PromoCodeRepository::firstOrCreate(
                ['promo_code' => $promoCode],
                [
                    'season_id' => $seasonId,
                    'start_date' => $startDate->toDateString(),
                    'expiration_date' => $expirationDate->toDateString(),
                    'country' => $country,
                    'is_suspended' => false,
                ]
            );

            $imported++;
        }

        return response()->json([
            'message' => "Imported {$imported} promo code(s)." . ($skipped > 0 ? " Skipped {$skipped}." : ''),
            'imported' => $imported,
            'skipped' => $skipped,
            'errors' => $errors,
        ]);
    }

    /**
     * Auto-assign promo codes from the repository to pass requests that don't have one.
     */
    public function autoAssign(Request $request, int $seasonId)
    {
        $season = Season::findOrFail($seasonId);

        $validated = $request->validate([
            'country' => ['sometimes', 'string', 'in:USA,Canada'],
        ]);

        $country = $validated['country'] ?? null;

        // Get available (non-suspended, unassigned) codes for this season
        $availableCodes = PromoCodeRepository::where('season_id', $seasonId)
            ->where('is_suspended', false)
            ->when($country, fn($q) => $q->where('country', $country))
            ->whereDoesntHave('passRequests')
            ->orderBy('start_date')
            ->orderBy('promo_code')
            ->get();

        // Get pass requests without codes for this season
        $passRequestsQuery = \App\Models\PassRequest::where('season_id', $seasonId)
            ->whereNull('promo_code');

        if ($country) {
            $passRequestsQuery->where('country', $country);
        }

        $passRequests = $passRequestsQuery->orderBy('created_at')->get();

        if ($availableCodes->isEmpty()) {
            return response()->json(['message' => 'No available promo codes in repository.'], 422);
        }

        $assigned = 0;
        foreach ($passRequests as $index => $passRequest) {
            if ($index >= $availableCodes->count()) {
                break;
            }

            $code = $availableCodes[$index];
            $passRequest->update([
                'promo_code' => $code->promo_code,
                'assign_code_date' => now(),
            ]);
            $assigned++;
        }

        return response()->json([
            'message' => "Auto-assigned {$assigned} promo code(s).",
            'assigned' => $assigned,
        ]);
    }
}
