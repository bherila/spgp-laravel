<?php

namespace App\Http\Controllers;

use App\Models\InviteCode;
use App\Models\PassRequest;
use App\Models\PromoCodeRepository;
use App\Models\Season;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    private const LOW_PROMO_CODE_THRESHOLD = 10;

    /**
     * Display the dashboard or specific season view.
     */
    public function index(Request $request, $seasonId = null)
    {
        $user = $request->user();

        if ($seasonId) {
            $season = Season::findOrFail($seasonId);

            if (! $user->isAdmin()) {
                $now = now();
                $threeMonthsFromNow = $now->copy()->addMonths(3);

                $isActive = ($season->start_date <= $now && $season->final_deadline >= $now) ||
                            ($season->start_date > $now && $season->start_date <= $threeMonthsFromNow) ||
                            $season->passRequests()->where('user_id', $user->id)->exists();

                if (! $isActive) {
                    abort(403, 'You do not have access to this season.');
                }
            }
        }

        $seasons = $this->buildSeasonData($user);

        if ($request->wantsJson()) {
            return response()->json($seasons);
        }

        return view('dashboard', [
            'seasonId' => $seasonId,
            'isQuestionsView' => $request->routeIs('questions.index'),
            'adminData' => $user->isAdmin() ? $this->buildAdminDashboardData() : null,
            'seasons' => $seasons,
        ]);
    }

    private function buildAdminDashboardData(): array
    {
        // Pass requests that have no promo code assigned yet, grouped by season
        $unassignedRequests = PassRequest::whereNull('promo_code')
            ->join('seasons', 'pass_requests.season_id', '=', 'seasons.id')
            ->selectRaw('pass_requests.season_id, seasons.pass_name, seasons.pass_year, COUNT(*) as count')
            ->groupBy('pass_requests.season_id', 'seasons.pass_name', 'seasons.pass_year')
            ->get()
            ->map(fn ($r) => [
                'season_id' => $r->season_id,
                'season_name' => $r->pass_name.' '.$r->pass_year,
                'count' => (int) $r->count,
            ])
            ->values();

        // Questions with no answer yet, grouped by season (filter >0 in PHP for SQLite compatibility)
        $unansweredQuestions = Season::withCount(['questions' => fn ($q) => $q->whereNull('answered_at')])
            ->get(['id', 'pass_name', 'pass_year'])
            ->filter(fn ($s) => $s->questions_count > 0)
            ->map(fn ($s) => [
                'season_id' => $s->id,
                'season_name' => $s->display_name,
                'count' => (int) $s->questions_count,
            ])
            ->values();

        // Available = non-suspended codes not yet used in any pass request
        $availableCounts = PromoCodeRepository::where('is_suspended', false)
            ->whereDoesntHave('passRequests')
            ->selectRaw('season_id, COUNT(*) as available_count')
            ->groupBy('season_id')
            ->pluck('available_count', 'season_id');

        // Only flag seasons that actually use the promo code repository
        $seasonIdsWithCodes = PromoCodeRepository::query()
            ->distinct()
            ->pluck('season_id');

        $lowPromoCodes = Season::whereIn('id', $seasonIdsWithCodes)
            ->get(['id', 'pass_name', 'pass_year'])
            ->filter(fn ($s) => ((int) ($availableCounts[$s->id] ?? 0)) <= self::LOW_PROMO_CODE_THRESHOLD)
            ->map(fn ($s) => [
                'season_id' => $s->id,
                'season_name' => $s->display_name,
                'available' => (int) ($availableCounts[$s->id] ?? 0),
            ])
            ->values();

        return [
            'unassigned_requests' => $unassignedRequests,
            'unanswered_questions' => $unansweredQuestions,
            'low_promo_codes' => $lowPromoCodes,
        ];
    }

    private function buildSeasonData($user): Collection
    {
        $now = now();
        $startOfToday = $now->copy()->startOfDay();
        $threeMonthsFromNow = $now->copy()->addMonths(3);

        // Get seasons with their pass requests for this user
        $query = Season::withCount(['passRequests' => function ($query) use ($user) {
            $query->where('user_id', $user->id);
        }])
            ->withCount('questions')
            ->with(['passRequests' => function ($query) use ($user) {
                $query->where('user_id', $user->id)
                    ->with('seasonPassType')
                    ->orderBy('created_at', 'desc');
            }]);

        // If not admin, filter to active or relevant seasons
        if (! $user->isAdmin()) {
            $query->where(function ($query) use ($now, $startOfToday, $threeMonthsFromNow, $user) {
                // Include if it's currently active (started and not finished)
                // We use startOfDay for the deadline to be inclusive of the final day
                $query->where(function ($q) use ($now, $startOfToday) {
                    $q->where('start_date', '<=', $now)
                        ->where('final_deadline', '>=', $startOfToday);
                })
                // OR if it's upcoming but within 3 months
                    ->orWhere(function ($q) use ($now, $threeMonthsFromNow) {
                        $q->where('start_date', '>', $now)
                            ->where('start_date', '<=', $threeMonthsFromNow);
                    })
                // OR if the user has a pass request in it (even if it's old or too far)
                    ->orWhereHas('passRequests', function ($q) use ($user) {
                        $q->where('user_id', $user->id);
                    })
                // OR if the user has an invite code for this season
                    ->orWhereHas('inviteCodes', function ($q) use ($user) {
                        $q->whereHas('users', function ($uq) use ($user) {
                            $uq->where('users.id', $user->id);
                        });
                    });
            });
        }

        return $query->orderBy('pass_year', 'desc')
            ->orderBy('pass_name', 'asc')
            ->get();
    }

    /**
     * Redeem an invite code to gain access to a season.
     */
    public function redeemInviteCode(Request $request)
    {
        $validated = $request->validate([
            'invite_code' => ['required', 'string'],
        ]);

        $inviteCode = InviteCode::where('invite_code', $validated['invite_code'])->first();

        if (! $inviteCode) {
            return response()->json(['message' => 'Invalid invite code.'], 422);
        }

        if ($inviteCode->trashed()) {
            return response()->json(['message' => 'This invite code is no longer active.'], 422);
        }

        if (! $inviteCode->canBeUsed()) {
            return response()->json(['message' => 'This invite code has reached its maximum number of uses.'], 422);
        }

        $user = $request->user();

        // Check if user already has access via this invite code or other invite codes for the same season
        $alreadyHasAccess = $user->inviteCodes()
            ->where('season_id', $inviteCode->season_id)
            ->exists();

        if ($alreadyHasAccess) {
            return response()->json(['message' => 'You already have access to this season.'], 422);
        }

        $user->inviteCodes()->attach($inviteCode->id);

        return response()->json([
            'message' => 'Invite code redeemed successfully! You now have access to '.$inviteCode->season->display_name,
            'season' => $inviteCode->season,
        ]);
    }
}
