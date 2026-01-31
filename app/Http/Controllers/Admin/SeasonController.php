<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Season;
use Illuminate\Http\Request;

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
}
