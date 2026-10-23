<?php

namespace App\Http\Controllers;

use App\Models\Season;
use App\Services\PassRequests\UserPassRequestService;
use Illuminate\Http\Request;

class PassRequestController extends Controller
{
    public function __construct(private readonly UserPassRequestService $passRequests) {}

    public function showRequestForm(int $seasonId)
    {
        $season = Season::findOrFail($seasonId);

        return view('request', compact('season'));
    }

    public function list(Request $request)
    {
        return response()->json($this->passRequests->grouped($request->user()));
    }

    public function getActiveSeasons(Request $request)
    {
        return response()->json($this->passRequests->activeSeasons($request->user()));
    }

    public function getPassTypes(int $seasonId)
    {
        return response()->json($this->passRequests->passTypes($seasonId));
    }

    public function store(Request $request)
    {
        return response()->json($this->passRequests->create($request->user(), $request->all()), 201);
    }

    public function update(Request $request, string $id)
    {
        $actor = $request->user();
        $passRequest = $actor->isAdmin()
            ? $this->passRequests->updateForAdministration($actor, $id, $request->all())
            : $this->passRequests->update($actor, $id, $request->all());

        return response()->json($passRequest);
    }

    public function updateRenewalOrder(Request $request, string $id)
    {
        return response()->json($this->passRequests->setRenewalOrder($request->user(), $id, $request->all()));
    }

    public function removeRenewalOrder(Request $request, string $id)
    {
        return response()->json($this->passRequests->removeRenewalOrder($request->user(), $id));
    }

    public function destroy(Request $request, string $id)
    {
        $this->passRequests->cancel($request->user(), $id);

        return response()->json(['message' => 'Pass request cancelled successfully']);
    }

    public function updateCountry(Request $request)
    {
        $updated = $this->passRequests->updateCountry($request->user(), $request->all());

        return response()->json([
            'message' => 'Country updated for '.$updated.' pass request(s).',
            'updated' => $updated,
        ]);
    }
}
