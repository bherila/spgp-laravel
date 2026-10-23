<?php

namespace App\Services;

use App\Models\PromoCodeRepository;
use App\Models\Season;

class PromoCodeRepositoryData
{
    public function forSeason(int $seasonId): array
    {
        $season = Season::findOrFail($seasonId);
        $codes = PromoCodeRepository::where('season_id', $seasonId)
            ->withCount('passRequests')
            ->orderBy('start_date')
            ->orderBy('promo_code')
            ->get()
            ->map(function ($code) {
                $code->is_assigned = $code->pass_requests_count > 0;

                return $code;
            });

        return ['season' => $season, 'promo_codes' => $codes];
    }

    public function availableCount(int $seasonId): int
    {
        return PromoCodeRepository::where('season_id', $seasonId)
            ->where('is_suspended', false)
            ->whereDoesntHave('passRequests')
            ->count();
    }
}
