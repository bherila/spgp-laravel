<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\PassCodeNotification;
use App\Models\EmailLog;
use App\Models\PassRequest;
use App\Models\Season;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class SeasonController extends Controller
{
    /**
     * Display the seasons admin page.
     */
    public function index()
    {
        return view('admin.seasons');
    }

    /**
     * Get all seasons as JSON (including soft deleted for display).
     */
    public function list(Request $request)
    {
        $includeArchived = $request->boolean('include_archived', false);
        
        $query = Season::query();
        
        if ($includeArchived) {
            $query->withTrashed();
        }
        
        $seasons = $query->withCount('passRequests as pass_request_count')
            ->orderBy('pass_year', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($seasons);
    }

    /**
     * Store a new season.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'pass_name' => ['required', 'string', 'max:100'],
            'pass_year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'start_date' => ['required', 'date'],
            'early_spring_deadline' => ['required', 'date'],
            'final_deadline' => ['required', 'date'],
            'spreadsheet_url' => ['nullable', 'string', 'max:500'],
            'allow_renewals' => ['nullable', 'boolean'],
        ]);

        $season = Season::create($validated);

        return response()->json($season, 201);
    }

    /**
     * Update an existing season.
     */
    public function update(Request $request, int $id)
    {
        $season = Season::withTrashed()->findOrFail($id);

        $validated = $request->validate([
            'pass_name' => ['sometimes', 'string', 'max:100'],
            'pass_year' => ['sometimes', 'integer', 'min:2000', 'max:2100'],
            'start_date' => ['sometimes', 'date'],
            'early_spring_deadline' => ['sometimes', 'date'],
            'final_deadline' => ['sometimes', 'date'],
            'spreadsheet_url' => ['nullable', 'string', 'max:500'],
            'allow_renewals' => ['nullable', 'boolean'],
        ]);

        $season->update($validated);

        return response()->json($season);
    }

    /**
     * Archive (soft delete) a season.
     */
    public function archive(int $id)
    {
        $season = Season::findOrFail($id);
        $season->delete();

        return response()->json(['message' => 'Season archived successfully']);
    }

    /**
     * Restore a soft-deleted season.
     */
    public function restore(int $id)
    {
        $season = Season::withTrashed()->findOrFail($id);
        $season->restore();

        return response()->json($season);
    }

    /**
     * Display the pass requests admin page for a season.
     */
    public function showPassRequests(int $id)
    {
        $season = Season::findOrFail($id);
        return view('admin.season-pass-requests', ['season' => $season]);
    }

    /**
     * Get all pass requests for a season.
     */
    public function listPassRequests(Request $request, int $id)
    {
        $season = Season::findOrFail($id);
        
        $query = PassRequest::where('season_id', $id)
            ->with(['user:id,name,email', 'seasonPassType']);

        // Filter by is_renewal if specified
        if ($request->has('is_renewal')) {
            $query->where('is_renewal', $request->boolean('is_renewal'));
        }

        // Filter by recently assigned (last 24 hours)
        if ($request->boolean('recent_only')) {
            $query->where('assign_code_date', '>=', now()->subDay());
        }

        // Filter by has promo code
        if ($request->has('has_promo_code')) {
            if ($request->boolean('has_promo_code')) {
                $query->whereNotNull('promo_code');
            } else {
                $query->whereNull('promo_code');
            }
        }

        $passRequests = $query->orderBy('created_at', 'desc')->get();

        return response()->json([
            'season' => $season,
            'pass_requests' => $passRequests,
        ]);
    }

    /**
     * Assign promo codes to pass requests.
     */
    public function assignCodes(Request $request, int $id)
    {
        $season = Season::findOrFail($id);

        $validated = $request->validate([
            'pass_request_ids' => ['required', 'array'],
            'pass_request_ids.*' => ['required', 'string'],
            'codes' => ['required', 'string'], // Newline-separated codes
        ]);

        $codes = array_filter(array_map('trim', explode("\n", $validated['codes'])));
        $passRequestIds = $validated['pass_request_ids'];

        if (count($codes) < count($passRequestIds)) {
            return response()->json([
                'message' => 'Not enough codes provided. Need ' . count($passRequestIds) . ' but got ' . count($codes),
            ], 422);
        }

        $assigned = 0;
        foreach ($passRequestIds as $index => $passRequestId) {
            $passRequest = PassRequest::where('id', $passRequestId)
                ->where('season_id', $id)
                ->first();

            if ($passRequest && isset($codes[$index])) {
                $passRequest->update([
                    'promo_code' => $codes[$index],
                    'assign_code_date' => now(),
                ]);
                $assigned++;
            }
        }

        return response()->json([
            'message' => "Assigned codes to {$assigned} pass requests.",
            'assigned' => $assigned,
        ]);
    }

    /**
     * Clear promo codes from pass requests.
     */
    public function clearCodes(Request $request, int $id)
    {
        $season = Season::findOrFail($id);

        $validated = $request->validate([
            'pass_request_ids' => ['required', 'array'],
            'pass_request_ids.*' => ['required', 'string'],
        ]);

        $cleared = PassRequest::whereIn('id', $validated['pass_request_ids'])
            ->where('season_id', $id)
            ->update([
                'promo_code' => null,
                'assign_code_date' => null,
            ]);

        return response()->json([
            'message' => "Cleared codes from {$cleared} pass requests.",
            'cleared' => $cleared,
        ]);
    }

    /**
     * Send email notifications to pass requests with codes.
     */
    public function sendEmails(Request $request, int $id)
    {
        $season = Season::findOrFail($id);

        $validated = $request->validate([
            'pass_request_ids' => ['required', 'array'],
            'pass_request_ids.*' => ['required', 'string'],
            'force_send' => ['sometimes', 'boolean'],
        ]);

        $forceSend = $validated['force_send'] ?? false;

        $query = PassRequest::with('user')
            ->whereIn('id', $validated['pass_request_ids'])
            ->where('season_id', $id)
            ->whereNotNull('promo_code');

        if (!$forceSend) {
            $query->whereNull('email_notify_time');
        }

        $passRequests = $query->get();

        $requestingUser = $request->user();

        $sent = 0;
        foreach ($passRequests as $passRequest) {
            try {
                $toAddresses = [$passRequest->passholder_email];
                if ($requestingUser !== null && $requestingUser->email !== $passRequest->passholder_email) {
                    $toAddresses[] = $requestingUser->email;
                }

                Mail::to($toAddresses)
                    ->send(new PassCodeNotification($passRequest, $season));

                $passRequest->update(['email_notify_time' => now()]);

                // Log the email
                EmailLog::create([
                    'event' => 'pass_code_notification',
                    'email_to' => implode(', ', $toAddresses),
                    'email_from' => config('mail.from.address', 'noreply@example.com'),
                    'subject' => "Your {$season->pass_name} {$season->pass_year} Promo Code",
                    'body' => "Promo code: {$passRequest->promo_code}",
                    'result' => 'sent',
                ]);

                $sent++;
            } catch (\Exception $e) {
                // Log failure but continue
                EmailLog::create([
                    'event' => 'pass_code_notification',
                    'email_to' => $passRequest->passholder_email,
                    'email_from' => config('mail.from.address', 'noreply@example.com'),
                    'subject' => "Your {$season->pass_name} {$season->pass_year} Promo Code",
                    'body' => "Failed: " . $e->getMessage(),
                    'result' => 'failed',
                    'error_message' => $e->getMessage(),
                ]);
            }
        }

        return response()->json([
            'message' => "Sent emails to {$sent} passholders.",
            'sent' => $sent,
        ]);
    }

    /**
     * Delete a pass request (admin only).
     */
    public function deletePassRequest(string $id)
    {
        $passRequest = PassRequest::findOrFail($id);
        $passRequest->delete();

        return response()->json(['message' => 'Pass request deleted successfully']);
    }
}
