<?php

namespace App\Http\Controllers;

use App\Models\PassRequest;
use App\Models\Season;
use Illuminate\Http\Request;

class PassRequestController extends Controller
{
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
            ->orderBy('pass_year', 'desc')
            ->get();

        return response()->json($seasons);
    }

    /**
     * Store a new pass request for the authenticated user.
     */
    public function store(Request $request)
    {
        $user = $request->user();
        
        $validated = $request->validate([
            'season_id' => ['required', 'exists:seasons,id'],
            'passholder_email' => ['required', 'email', 'max:255'],
            'pass_type' => ['required', 'string', 'max:255'],
            'passholder_first_name' => ['required', 'string', 'max:255'],
            'passholder_last_name' => ['required', 'string', 'max:255'],
            'passholder_birth_date' => ['required', 'date'],
            'is_renewal' => ['sometimes', 'boolean'],
            'renewal_pass_id' => ['nullable', 'string', 'max:255'],
        ]);

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

        // Only allow deletion if no promo code or renewal order number assigned
        if ($passRequest->promo_code || $passRequest->renewal_order_number) {
            return response()->json(['message' => 'Cannot delete a pass request with a code assigned'], 400);
        }

        $passRequest->delete();

        return response()->json(['message' => 'Pass request deleted successfully']);
    }
}
