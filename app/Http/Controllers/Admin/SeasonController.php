<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Season;
use App\Services\PassRequests\AdminPassRequestService;
use Illuminate\Http\Request;

class SeasonController extends Controller
{
    public function __construct(private AdminPassRequestService $passRequests) {}

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
        return response()->json($this->passRequests->seasons($request->user(), $request->query()));
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
            'spreadsheet_url' => ['nullable', 'string', 'max:500'],
            'allow_renewals' => ['nullable', 'boolean'],
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
            'spreadsheet_url' => ['nullable', 'string', 'max:500'],
            'allow_renewals' => ['nullable', 'boolean'],
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

    /**
     * Display the pass requests admin page for a season.
     */
    public function showPassRequests(int $id)
    {
        $season = Season::findOrFail($id);

        return view('admin.season-pass-requests', ['season' => $season]);
    }

    /**
     * Get all pass requests for a season.
     */
    public function listPassRequests(Request $request, int $id)
    {
        return response()->json($this->passRequests->listForSeason($request->user(), $id, $request->query()));
    }

    public function assignCodes(Request $request, int $id)
    {
        $assigned = $this->passRequests->assignCodes($request->user(), $id, $request->all());

        return response()->json([
            'message' => "Assigned codes to {$assigned} pass requests.",
            'assigned' => $assigned,
        ]);
    }

    public function clearCodes(Request $request, int $id)
    {
        $cleared = $this->passRequests->clearCodes($request->user(), $id, $request->all());

        return response()->json([
            'message' => "Cleared codes from {$cleared} pass requests.",
            'cleared' => $cleared,
        ]);
    }

    public function sendEmails(Request $request, int $id)
    {
        $sent = $this->passRequests->sendEmails($request->user(), $id, $request->all());

        return response()->json([
            'message' => "Sent emails to {$sent} passholders.",
            'sent' => $sent,
        ]);
    }

    public function deletePassRequest(Request $request, string $id)
    {
        $this->passRequests->delete($request->user(), $id);

        return response()->json(['message' => 'Pass request deleted successfully']);
    }

    public function bulkDeletePassRequests(Request $request, int $id)
    {
        $count = $this->passRequests->bulkDelete($request->user(), $id, $request->all());

        return response()->json([
            'message' => "Deleted {$count} pass request(s).",
            'deleted' => $count,
        ]);
    }
}
