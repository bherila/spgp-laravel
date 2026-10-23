<?php

namespace App\Services\PassRequests;

use App\Models\PassRequest;
use App\Models\Season;
use App\Models\SeasonPassType;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UserPassRequestService
{
    public function grouped(User $actor): Collection
    {
        return Season::query()
            ->whereHas('passRequests', fn (Builder $query) => $query->where('user_id', $actor->id))
            ->with(['passRequests' => function ($query) use ($actor): void {
                $query->where('user_id', $actor->id)
                    ->with('seasonPassType')
                    ->orderByDesc('created_at');
            }])
            ->orderByDesc('pass_year')
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * The user surface always stays owner scoped, including for administrator actors.
     */
    public function query(User $actor, array $filters = []): Builder
    {
        $validated = Validator::make($filters, [
            'season_id' => ['sometimes', 'integer', 'min:1'],
        ])->validate();

        return PassRequest::query()
            ->where('user_id', $actor->id)
            ->whereHas('season')
            ->when(isset($validated['season_id']), fn (Builder $query) => $query->where('season_id', $validated['season_id']))
            ->with(['season', 'seasonPassType'])
            ->orderByDesc('created_at')
            ->orderByDesc('id');
    }

    public function get(User $actor, string $id): PassRequest
    {
        $passRequest = PassRequest::findOrFail($id);
        $this->authorizeOwner($actor, $passRequest);

        return $passRequest->load(['season', 'seasonPassType']);
    }

    public function activeSeasons(User $actor): Collection
    {
        $now = now('UTC');
        $seasons = Season::query()
            ->where('final_deadline', '>=', $now)
            ->where('start_date', '<=', $now->copy()->addMonths(3))
            ->with(['passTypes' => fn ($query) => $query->orderBy('sort_order')])
            ->orderByDesc('pass_year')
            ->get();

        $latestPerSeason = PassRequest::selectRaw('MAX(created_at) as latest_at, season_id')
            ->where('user_id', $actor->id)
            ->whereIn('season_id', $seasons->modelKeys())
            ->groupBy('season_id');

        // Equal timestamp ties resolve to the highest ID when pluck builds its map.
        $previousPassTypes = PassRequest::joinSub($latestPerSeason, 'latest', function ($join): void {
            $join->on('pass_requests.season_id', '=', 'latest.season_id')
                ->on('pass_requests.created_at', '=', 'latest.latest_at');
        })
            ->where('pass_requests.user_id', $actor->id)
            ->orderBy('pass_requests.id')
            ->pluck('pass_requests.season_pass_type_id', 'pass_requests.season_id');

        $seasons->each(function (Season $season) use ($previousPassTypes): void {
            $season->user_previous_pass_type_id = $previousPassTypes->get($season->id);
        });

        return $seasons;
    }

    public function passTypes(int $seasonId): Collection
    {
        return SeasonPassType::where('season_id', $seasonId)->orderBy('sort_order')->get();
    }

    public function create(User $actor, array $input): PassRequest
    {
        $validated = Validator::make($input, [
            'season_id' => ['bail', 'required', 'integer', Rule::exists('seasons', 'id')->whereNull('deleted_at')],
            'season_pass_type_id' => ['bail', 'required', 'integer', Rule::exists('season_pass_types', 'id')->where('season_id', is_scalar($input['season_id'] ?? null) ? $input['season_id'] : null)],
            'passholder_email' => ['required', 'email', 'max:255'],
            'passholder_first_name' => ['required', 'string', 'max:255'],
            'passholder_last_name' => ['required', 'string', 'max:255'],
            'passholder_birth_date' => ['required', 'date'],
            'is_renewal' => ['sometimes', 'boolean'],
            'renewal_pass_id' => ['nullable', 'string', 'max:255'],
            'country' => ['required', 'string', 'in:USA,Canada,Other'],
        ])->validate();

        $validated['passholder_birth_date'] = Carbon::parse($validated['passholder_birth_date'])->toDateString();

        return DB::transaction(function () use ($actor, $validated): PassRequest {
            // Serialize identity changes within the season without a schema change.
            Season::whereKey($validated['season_id'])->lockForUpdate()->firstOrFail();
            $this->ensureUniqueIdentity($actor, $validated);

            $passType = SeasonPassType::findOrFail($validated['season_pass_type_id']);

            return PassRequest::create([
                ...$validated,
                'pass_type' => $passType->pass_type_name,
                'user_id' => $actor->id,
            ]);
        });
    }

    public function update(User $actor, string $id, array $input): PassRequest
    {
        $passRequest = PassRequest::findOrFail($id);
        $this->authorizeOwner($actor, $passRequest);

        return $this->updateDetails($actor, $passRequest, $input);
    }

    /**
     * Explicit administrative entry point for the existing browser administration flow.
     */
    public function updateForAdministration(User $actor, string $id, array $input): PassRequest
    {
        if (! $actor->isAdmin()) {
            throw new AuthorizationException('Unauthorized');
        }

        return $this->updateDetails($actor, PassRequest::findOrFail($id), $input);
    }

    public function setRenewalOrder(User $actor, string $id, array $input): PassRequest
    {
        $passRequest = PassRequest::findOrFail($id);
        $this->authorizeOwner($actor, $passRequest);
        $validated = Validator::make($input, [
            'renewal_order_number' => ['required', 'string', 'max:30'],
        ])->validate();

        $passRequest->update([
            'renewal_order_number' => $validated['renewal_order_number'],
            'assign_code_date' => now(),
        ]);

        return $passRequest;
    }

    public function removeRenewalOrder(User $actor, string $id): PassRequest
    {
        $passRequest = PassRequest::findOrFail($id);
        $this->authorizeOwner($actor, $passRequest);
        $passRequest->update(['renewal_order_number' => null, 'assign_code_date' => null]);

        return $passRequest;
    }

    public function cancel(User $actor, string $id): void
    {
        DB::transaction(function () use ($actor, $id): void {
            $passRequest = PassRequest::whereKey($id)->lockForUpdate()->firstOrFail();
            $this->authorizeOwner($actor, $passRequest);

            if ($passRequest->promo_code) {
                throw ValidationException::withMessages([
                    'pass_request' => 'This pass request cannot be cancelled because a promo code has already been assigned.',
                ]);
            }

            $passRequest->delete();
        });
    }

    public function updateCountry(User $actor, array $input): int
    {
        $validated = Validator::make($input, [
            'country' => ['required', 'string', 'in:USA,Canada'],
        ])->validate();

        return DB::transaction(function () use ($actor, $validated): int {
            $passRequests = PassRequest::where('user_id', $actor->id)->whereNull('country')->lockForUpdate()->get();

            foreach ($passRequests as $passRequest) {
                $changes = ['country' => $validated['country']];

                if ($validated['country'] !== 'USA' && $passRequest->promo_code) {
                    $changes += ['promo_code' => null, 'assign_code_date' => null, 'email_notify_time' => null];
                }

                $passRequest->update($changes);
            }

            return $passRequests->count();
        });
    }

    private function authorizeOwner(User $actor, PassRequest $passRequest): void
    {
        if ($passRequest->user_id !== $actor->id) {
            throw new AuthorizationException('Unauthorized');
        }
    }

    private function updateDetails(User $actor, PassRequest $passRequest, array $input): PassRequest
    {
        $validated = Validator::make($input, [
            'renewal_order_number' => ['nullable', 'string', 'max:30'],
            'passholder_email' => ['sometimes', 'email', 'max:255'],
            'passholder_first_name' => ['sometimes', 'string', 'max:255'],
            'passholder_last_name' => ['sometimes', 'string', 'max:255'],
            'passholder_birth_date' => ['sometimes', 'date'],
        ])->validate();

        if (array_key_exists('passholder_birth_date', $validated)) {
            $validated['passholder_birth_date'] = Carbon::parse($validated['passholder_birth_date'])->toDateString();
        }

        return DB::transaction(function () use ($actor, $passRequest, $validated): PassRequest {
            Season::withTrashed()->whereKey($passRequest->season_id)->lockForUpdate()->firstOrFail();
            $passRequest->refresh();
            $identity = [
                'season_id' => $passRequest->season_id,
                'passholder_first_name' => $passRequest->passholder_first_name,
                'passholder_last_name' => $passRequest->passholder_last_name,
                'passholder_email' => $passRequest->passholder_email,
                'passholder_birth_date' => $passRequest->passholder_birth_date->toDateString(),
                ...$validated,
            ];
            $this->ensureUniqueIdentity($actor, $identity, $passRequest->id);
            $passRequest->update($validated);

            return $passRequest;
        });
    }

    private function ensureUniqueIdentity(User $actor, array $identity, ?string $exceptId = null): void
    {
        $existingRequest = PassRequest::query()
            ->where('season_id', $identity['season_id'])
            ->where('passholder_first_name', $identity['passholder_first_name'])
            ->where('passholder_last_name', $identity['passholder_last_name'])
            ->where('passholder_email', $identity['passholder_email'])
            ->whereDate('passholder_birth_date', $identity['passholder_birth_date'])
            ->when($exceptId !== null, fn (Builder $query) => $query->whereKeyNot($exceptId))
            ->first();

        if ($existingRequest !== null) {
            $message = $existingRequest->user_id === $actor->id
                ? 'You have already created a pass request for this person. Only one pass request can exist for a Name/Email/DOB per Season.'
                : 'Another user has already created a pass request for this person. Please ensure they haven\'t already requested their own pass. Only one pass request can exist for a Name/Email/DOB per Season.';

            throw ValidationException::withMessages(['passholder_email' => $message]);
        }
    }
}
