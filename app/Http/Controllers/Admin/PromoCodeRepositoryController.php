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
            ->withCount('passRequests')
            ->orderBy('start_date')
            ->orderBy('promo_code')
            ->get()
            ->map(function ($code) {
                $code->is_assigned = $code->pass_requests_count > 0;
                return $code;
            });

        return response()->json([
            'season' => $season,
            'promo_codes' => $codes,
        ]);
    }

    /**
     * Import promo codes from TSV paste.
     * Expected TSV columns: Code, Date Added (Date Added is optional; defaults to today)
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

            $promoCode = trim($parts[0] ?? '');
            $startDateRaw = trim($parts[1] ?? '');

            // Skip header row if present on first non-empty row
            // (e.g. "Code" or "Code\tDate Added"), while allowing real promo
            // codes like "CODEONLY1".
            if ($imported === 0 && $skipped === 0 && empty($errors) &&
                (strtolower($promoCode) === 'code' || stripos($startDateRaw, 'date') !== false)) {
                continue;
            }

            if (empty($promoCode)) {
                continue;
            }

            // Parse start date — fall back to today if missing or blank
            if ($startDateRaw === '') {
                $startDate = Carbon::today();
            } else {
                try {
                    $startDate = Carbon::parse($startDateRaw);
                } catch (\Exception $e) {
                    $errors[] = "Line " . ($lineIndex + 1) . ": invalid date '{$startDateRaw}'";
                    continue;
                }
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
     * Codes are matched to pass requests by country. Pass requests with a null country
     * are treated as USA, but the country field on the pass request is NOT updated.
     */
    public function autoAssign(Request $request, int $seasonId)
    {
        Season::findOrFail($seasonId);

        // Derive the set of countries to process from what's actually in the repository,
        // so no country values need to be hardcoded here.
        $countries = PromoCodeRepository::where('season_id', $seasonId)
            ->where('is_suspended', false)
            ->whereDoesntHave('passRequests')
            ->distinct()
            ->pluck('country');

        $assigned = 0;

        foreach ($countries as $c) {
            $availableCodes = PromoCodeRepository::where('season_id', $seasonId)
                ->where('is_suspended', false)
                ->where('country', $c)
                ->whereDoesntHave('passRequests')
                ->orderBy('start_date')
                ->orderBy('promo_code')
                ->get();

            $passRequestsQuery = \App\Models\PassRequest::where('season_id', $seasonId)
                ->whereNull('promo_code');

            if ($c === 'USA') {
                // Null-country pass requests default to USA.
                $passRequestsQuery->where(function ($q) {
                    $q->where('country', 'USA')->orWhereNull('country');
                });
            } else {
                $passRequestsQuery->where('country', $c);
            }

            $passRequests = $passRequestsQuery->orderBy('created_at')->get();

            foreach ($passRequests as $index => $passRequest) {
                if ($index >= $availableCodes->count()) {
                    break;
                }

                $code = $availableCodes[$index];
                // Do NOT update the country field — just assign the promo code.
                $passRequest->update([
                    'promo_code' => $code->promo_code,
                    'assign_code_date' => now(),
                ]);
                $assigned++;
            }
        }

        return response()->json([
            'message' => "Auto-assigned {$assigned} promo code(s).",
            'assigned' => $assigned,
        ]);
    }
}
