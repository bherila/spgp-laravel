<?php

namespace App\Services\Questions;

use App\Models\Season;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class QuestionQuery
{
    public function dataForSeason(Season $season, User $user): Collection
    {
        return $season->questions()
            ->with(['user:id,first_name,last_name', 'answeredBy:id,first_name,last_name'])
            ->withCount('upvotes')
            ->withExists(['upvotes as user_has_upvoted' => fn ($query) => $query->where('user_id', $user->id)])
            ->orderBy('upvotes_count', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();
    }
}
