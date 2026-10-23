<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\InviteCode;
use App\Services\PassRequests\AdminPassRequestService;
use Illuminate\Http\Request;

class InviteCodeController extends Controller
{
    /**
     * Display the invite codes admin page.
     */
    public function index(Request $request, AdminPassRequestService $passRequests)
    {
        return view('admin.invites', ['pageData' => [
            'inviteCodes' => $this->inviteCodes(false),
            'seasons' => $passRequests->seasons($request->user()),
        ]]);
    }

    /**
     * Get all invite codes as JSON.
     */
    public function list(Request $request)
    {
        return response()->json($this->inviteCodes($request->boolean('include_archived', false)));
    }

    private function inviteCodes(bool $includeArchived)
    {
        $query = InviteCode::query();

        if ($includeArchived) {
            $query->withTrashed();
        }

        return $query->with(['season'])
            ->withCount('users as usage_count')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Store a new invite code.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'season_id' => ['required', 'exists:seasons,id'],
            'invite_code' => ['required', 'string', 'max:255', 'unique:invite_codes'],
            'max_number_of_uses' => ['required', 'integer', 'min:1'],
        ]);

        $inviteCode = InviteCode::create($validated);

        return response()->json($inviteCode->load('season'), 201);
    }

    /**
     * Update an existing invite code.
     */
    public function update(Request $request, int $id)
    {
        $inviteCode = InviteCode::withTrashed()->findOrFail($id);

        $validated = $request->validate([
            'season_id' => ['sometimes', 'exists:seasons,id'],
            'invite_code' => ['sometimes', 'string', 'max:255', 'unique:invite_codes,invite_code,'.$id],
            'max_number_of_uses' => ['sometimes', 'integer', 'min:1'],
        ]);

        $inviteCode->update($validated);

        return response()->json($inviteCode->load('season'));
    }

    /**
     * Archive (soft delete) an invite code.
     */
    public function archive(int $id)
    {
        $inviteCode = InviteCode::findOrFail($id);
        $inviteCode->delete();

        return response()->json(['message' => 'Invite code archived successfully']);
    }

    /**
     * Restore a soft-deleted invite code.
     */
    public function restore(int $id)
    {
        $inviteCode = InviteCode::withTrashed()->findOrFail($id);
        $inviteCode->restore();

        return response()->json($inviteCode->load('season'));
    }
}
