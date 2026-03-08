<?php

namespace App\Http\Controllers;

use App\Models\Season;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    /**
     * Display the dashboard or specific season view.
     */
    public function index(Request $request, $seasonId = null)
    {
        if ($seasonId) {
            $season = Season::findOrFail($seasonId);
            $user = $request->user();

            if (!$user->isAdmin()) {
                $now = now();
                $threeMonthsFromNow = $now->copy()->addMonths(3);
                
                $isActive = ($season->start_date <= $now && $season->final_deadline >= $now) ||
                            ($season->start_date > $now && $season->start_date <= $threeMonthsFromNow) ||
                            $season->passRequests()->where('user_id', $user->id)->exists();

                if (!$isActive) {
                    abort(403, 'You do not have access to this season.');
                }
            }
        }

        return view('dashboard', [
            'seasonId' => $seasonId,
            'isQuestionsView' => $request->routeIs('questions.index'),
        ]);
    }

    /**
     * Get seasons with user's pass requests for the dashboard.
     */
    public function passRequests(Request $request)
    {
        $user = $request->user();
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
        if (!$user->isAdmin()) {
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
        
        $seasons = $query->orderBy('pass_year', 'desc')
            ->orderBy('pass_name', 'asc')
            ->get();
        
        return response()->json($seasons);
    }

    /**
     * Redeem an invite code to gain access to a season.
     */
    public function redeemInviteCode(Request $request)
    {
        $validated = $request->validate([
            'invite_code' => ['required', 'string'],
        ]);

        $inviteCode = \App\Models\InviteCode::where('invite_code', $validated['invite_code'])->first();

        if (!$inviteCode) {
            return response()->json(['message' => 'Invalid invite code.'], 422);
        }

        if ($inviteCode->trashed()) {
            return response()->json(['message' => 'This invite code is no longer active.'], 422);
        }

        if (!$inviteCode->canBeUsed()) {
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
            'message' => 'Invite code redeemed successfully! You now have access to ' . $inviteCode->season->display_name,
            'season' => $inviteCode->season
        ]);
    }
}
