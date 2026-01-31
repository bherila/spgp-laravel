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
        
        // Get active (non-archived) seasons with their pass requests for this user
        $seasons = Season::withCount(['passRequests' => function ($query) use ($user) {
            $query->where('user_id', $user->id);
        }])
        ->with(['passRequests' => function ($query) use ($user) {
            $query->where('user_id', $user->id)
                  ->orderBy('created_at', 'desc');
        }])
        ->orderBy('pass_year', 'desc')
        ->orderBy('pass_name', 'asc')
        ->get();
        
        return response()->json($seasons);
    }
}
