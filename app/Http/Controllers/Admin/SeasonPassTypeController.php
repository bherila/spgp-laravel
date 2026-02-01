<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SeasonPassType;
use App\Models\Season;
use Illuminate\Http\Request;

class SeasonPassTypeController extends Controller
{
    /**
     * Get all pass types for a season.
     */
    public function list(int $seasonId)
    {
        $passTypes = SeasonPassType::where('season_id', $seasonId)
            ->orderBy('sort_order')
            ->get();

        return response()->json($passTypes);
    }

    /**
     * Store a new pass type for a season.
     */
    public function store(Request $request, int $seasonId)
    {
        $season = Season::findOrFail($seasonId);

        $validated = $request->validate([
            'pass_type_name' => ['required', 'string', 'max:255'],
            'regular_price' => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'group_early_price' => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'group_price' => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ]);

        $validated['season_id'] = $season->id;

        // Auto-increment sort_order if not provided
        if (!isset($validated['sort_order'])) {
            $maxSort = SeasonPassType::where('season_id', $seasonId)->max('sort_order');
            $validated['sort_order'] = ($maxSort ?? -1) + 1;
        }

        $passType = SeasonPassType::create($validated);

        return response()->json($passType, 201);
    }

    /**
     * Update a pass type.
     */
    public function update(Request $request, int $id)
    {
        $passType = SeasonPassType::findOrFail($id);

        $validated = $request->validate([
            'pass_type_name' => ['sometimes', 'string', 'max:255'],
            'regular_price' => ['sometimes', 'numeric', 'min:0', 'max:999999.99'],
            'group_early_price' => ['sometimes', 'numeric', 'min:0', 'max:999999.99'],
            'group_price' => ['sometimes', 'numeric', 'min:0', 'max:999999.99'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ]);

        $passType->update($validated);

        return response()->json($passType);
    }

    /**
     * Delete a pass type.
     */
    public function destroy(int $id)
    {
        $passType = SeasonPassType::findOrFail($id);

        // Check if there are any pass requests using this pass type
        if ($passType->passRequests()->exists()) {
            return response()->json([
                'message' => 'Cannot delete pass type that has pass requests associated with it',
            ], 400);
        }

        $passType->delete();

        return response()->json(['message' => 'Pass type deleted successfully']);
    }
}
