<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\InviteCode;
use Illuminate\Http\Request;

class InviteCodeController extends Controller
{
    /**
     * Display the invite codes admin page.
     */
    public function index()
    {
        return view('admin.invites');
    }

    /**
     * Get all invite codes as JSON (including soft deleted for display).
     */
    public function list(Request $request)
    {
        $includeArchived = $request->boolean('include_archived', false);
        
        $query = InviteCode::query();
        
        if ($includeArchived) {
            $query->withTrashed();
        }
        
        $inviteCodes = $query->withCount('users as usage_count')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($inviteCodes);
    }

    /**
     * Store a new invite code.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'invite_code' => ['required', 'string', 'max:255', 'unique:invite_codes'],
            'max_number_of_uses' => ['required', 'integer', 'min:1'],
        ]);

        $inviteCode = InviteCode::create($validated);

        return response()->json($inviteCode, 201);
    }

    /**
     * Update an existing invite code.
     */
    public function update(Request $request, int $id)
    {
        $inviteCode = InviteCode::withTrashed()->findOrFail($id);

        $validated = $request->validate([
            'invite_code' => ['sometimes', 'string', 'max:255', 'unique:invite_codes,invite_code,' . $id],
            'max_number_of_uses' => ['sometimes', 'integer', 'min:1'],
        ]);

        $inviteCode->update($validated);

        return response()->json($inviteCode);
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

        return response()->json($inviteCode);
    }
}
