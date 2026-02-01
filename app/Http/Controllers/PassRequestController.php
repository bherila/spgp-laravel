<?php

namespace App\Http\Controllers;

use App\Models\PassRequest;
use App\Models\Season;
use App\Models\SeasonPassType;
use Illuminate\Http\Request;

class PassRequestController extends Controller
{
    /**
     * Show the pass request form.
     */
    public function showRequestForm(int $seasonId)
    {
        $season = Season::findOrFail($seasonId);
        return view('request', compact('season'));
    }

    /**
     * Get pass requests for the authenticated user, grouped by season.
     * Only returns non-archived seasons.
     */
    public function list(Request $request)
    {
        $user = $request->user();
        
        // Get active seasons with user's pass requests
        $seasons = Season::query()
            ->whereHas('passRequests', function ($query) use ($user) {
                $query->where('user_id', $user->id);
            })
            ->with(['passRequests' => function ($query) use ($user) {
                $query->where('user_id', $user->id)
                    ->with('seasonPassType')
                    ->orderBy('created_at', 'desc');
            }])
            ->orderBy('pass_year', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($seasons);
    }

    /**
     * Get all active seasons for the request form.
     */
    public function getActiveSeasons()
    {
        $now = now();
        
        $seasons = Season::query()
            ->where('final_deadline', '>=', $now)
            ->with(['passTypes' => function ($query) {
                $query->orderBy('sort_order');
            }])
            ->orderBy('pass_year', 'desc')
            ->get();

        return response()->json($seasons);
    }

    /**
     * Get pass types for a specific season.
     */
    public function getPassTypes(int $seasonId)
    {
        $passTypes = SeasonPassType::where('season_id', $seasonId)
            ->orderBy('sort_order')
            ->get();

        return response()->json($passTypes);
    }

    /**
     * Store a new pass request for the authenticated user.
     */
    public function store(Request $request)
    {
        $user = $request->user();
        
        $validated = $request->validate([
            'season_id' => ['required', 'exists:seasons,id'],
            'season_pass_type_id' => ['required', 'exists:season_pass_types,id'],
            'passholder_email' => ['required', 'email', 'max:255'],
            'passholder_first_name' => ['required', 'string', 'max:255'],
            'passholder_last_name' => ['required', 'string', 'max:255'],
            'passholder_birth_date' => ['required', 'date'],
            'is_renewal' => ['sometimes', 'boolean'],
            'renewal_pass_id' => ['nullable', 'string', 'max:255'],
        ]);

        // Get the pass type name for legacy compatibility
        $passType = SeasonPassType::find($validated['season_pass_type_id']);
        $validated['pass_type'] = $passType?->pass_type_name ?? '';
        $validated['user_id'] = $user->id;

        $passRequest = PassRequest::create($validated);

        return response()->json($passRequest, 201);
    }

    /**
     * Update a pass request (only by owner or admin).
     */
    public function update(Request $request, string $id)
    {
        $user = $request->user();
        $passRequest = PassRequest::findOrFail($id);

        // Check ownership or admin
        if ($passRequest->user_id !== $user->id && !$user->isAdmin()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'renewal_order_number' => ['nullable', 'string', 'max:30'],
            'passholder_email' => ['sometimes', 'email', 'max:255'],
            'passholder_first_name' => ['sometimes', 'string', 'max:255'],
            'passholder_last_name' => ['sometimes', 'string', 'max:255'],
            'passholder_birth_date' => ['sometimes', 'date'],
        ]);

        $passRequest->update($validated);

        return response()->json($passRequest);
    }

    /**
     * Update the renewal order number for a pass request (self-reported).
     */
    public function updateRenewalOrder(Request $request, string $id)
    {
        $user = $request->user();
        $passRequest = PassRequest::findOrFail($id);

        // Check ownership
        if ($passRequest->user_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'renewal_order_number' => ['required', 'string', 'max:30'],
        ]);

        $passRequest->update([
            'renewal_order_number' => $validated['renewal_order_number'],
            'assign_code_date' => now(),
        ]);

        return response()->json($passRequest);
    }

    /**
     * Remove the renewal order number from a pass request.
     */
    public function removeRenewalOrder(Request $request, string $id)
    {
        $user = $request->user();
        $passRequest = PassRequest::findOrFail($id);

        // Check ownership
        if ($passRequest->user_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $passRequest->update([
            'renewal_order_number' => null,
            'assign_code_date' => null,
        ]);

        return response()->json($passRequest);
    }

    /**
     * Delete a pass request (only by owner or admin).
     */
    public function destroy(Request $request, string $id)
    {
        $user = $request->user();
        $passRequest = PassRequest::findOrFail($id);

        // Check ownership or admin
        if ($passRequest->user_id !== $user->id && !$user->isAdmin()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Only allow deletion if no promo code assigned (renewal order can still be deleted)
        if ($passRequest->promo_code) {
            return response()->json(['message' => 'Cannot delete a pass request with a promo code assigned'], 400);
        }

        $passRequest->delete();

        return response()->json(['message' => 'Pass request deleted successfully']);
    }
}
