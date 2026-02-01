<?php

namespace App\Http\Controllers;

use App\Models\Season;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * Display the dashboard.
     */
    public function index(Request $request)
    {
        return view('dashboard');
    }

    /**
     * Get seasons with user's pass requests for the dashboard.
     */
    public function passRequests(Request $request)
    {
        $user = $request->user();
        $now = now();
        $threeMonthsFromNow = $now->copy()->addMonths(3);
        
        // Get active (non-archived) seasons with their pass requests for this user
        $seasons = Season::withCount(['passRequests' => function ($query) use ($user) {
            $query->where('user_id', $user->id);
        }])
        ->with(['passRequests' => function ($query) use ($user) {
            $query->where('user_id', $user->id)
                  ->with('seasonPassType')
                  ->orderBy('created_at', 'desc');
        }])
        ->where(function ($query) use ($now, $threeMonthsFromNow, $user) {
            // Include if it's currently active (started and not finished)
            $query->where(function ($q) use ($now) {
                $q->where('start_date', '<=', $now)
                  ->where('final_deadline', '>=', $now);
            })
            // OR if it's upcoming but within 3 months
            ->orWhere(function ($q) use ($now, $threeMonthsFromNow) {
                $q->where('start_date', '>', $now)
                  ->where('start_date', '<=', $threeMonthsFromNow);
            })
            // OR if the user has a pass request in it (even if it's old or too far)
            ->orWhereHas('passRequests', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            });
        })
        ->orderBy('pass_year', 'desc')
        ->orderBy('pass_name', 'asc')
        ->get();
        
        return response()->json($seasons);
    }
}
