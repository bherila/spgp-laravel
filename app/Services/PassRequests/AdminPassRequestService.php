<?php

namespace App\Services\PassRequests;

use App\Mail\PassCodeNotification;
use App\Models\EmailLog;
use App\Models\PassRequest;
use App\Models\PromoCodeRepository;
use App\Models\Season;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class AdminPassRequestService
{
    public function __construct(private UserPassRequestService $userRequests) {}

    public function seasons(User $actor, array $filters = []): Collection
    {
        $this->authorize($actor);
        $validated = Validator::make([
            'include_archived' => filter_var($filters['include_archived'] ?? false, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
        ], ['include_archived' => ['boolean']])->validate();

        return Season::query()
            ->when($validated['include_archived'], fn (Builder $query) => $query->withTrashed())
            ->withCount('passRequests as pass_request_count')
            ->orderByDesc('pass_year')->orderByDesc('created_at')->get();
    }

    public function listForSeason(User $actor, int $seasonId, array $filters = []): array
    {
        $this->authorize($actor);
        $season = Season::findOrFail($seasonId);

        return [
            'season' => $season,
            'pass_requests' => $this->filteredQuery(array_merge($filters, ['season_id' => $seasonId]))
                ->where('season_id', $seasonId)
                ->get(),
        ];
    }

    public function query(User $actor, array $filters = []): LengthAwarePaginator
    {
        $this->authorize($actor);
        $pagination = Validator::make($filters, [
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ])->validate();

        return $this->filteredQuery($filters)->paginate(
            $pagination['per_page'] ?? 25,
            ['*'],
            'page',
            $pagination['page'] ?? 1,
        );
    }

    public function get(User $actor, string $id): PassRequest
    {
        $this->authorize($actor);

        return PassRequest::with(['user:id,first_name,last_name,email', 'seasonPassType', 'season'])->findOrFail($id);
    }

    public function update(User $actor, string $id, array $data): PassRequest
    {
        $this->authorize($actor);

        return $this->userRequests->updateForAdministration($actor, $id, $data);
    }

    public function delete(User $actor, string $id): void
    {
        $this->authorize($actor);
        $request = PassRequest::findOrFail($id);
        DB::transaction(function () use ($id, $request) {
            Season::withTrashed()->whereKey($request->season_id)->lockForUpdate()->firstOrFail();
            PassRequest::whereKey($id)->lockForUpdate()->firstOrFail()->delete();
        });
    }

    public function bulkDelete(User $actor, int $seasonId, array $data): int
    {
        $this->authorize($actor);
        $ids = $this->selectedIds($data);

        return DB::transaction(function () use ($seasonId, $ids) {
            $requests = $this->lockSelection($seasonId, $ids);
            if ($requests->contains(fn (PassRequest $request) => $request->promo_code !== null)) {
                throw ValidationException::withMessages([
                    'pass_request_ids' => 'Cannot delete pass requests that have a promo code assigned. Please unassign the codes first.',
                ]);
            }

            return PassRequest::whereIn('id', $requests->modelKeys())->delete();
        });
    }

    public function assignCodes(User $actor, int $seasonId, array $data): int
    {
        $this->authorize($actor);
        $ids = $this->selectedIds($data);
        $validated = Validator::make($data, ['codes' => ['required', 'string']])->validate();
        $codes = array_values(array_filter(array_map('trim', explode("\n", $validated['codes'])), fn (string $code) => $code !== ''));
        if (count($codes) < count($ids)) {
            throw ValidationException::withMessages([
                'codes' => 'Not enough codes provided. Need '.count($ids).' but got '.count($codes),
            ]);
        }
        $codes = array_slice($codes, 0, count($ids));
        Validator::make(['codes' => $codes], [
            'codes.*' => ['string', 'max:255', 'distinct:strict'],
        ])->validate();

        return DB::transaction(function () use ($seasonId, $ids, $codes) {
            $requests = $this->lockSelection($seasonId, $ids)->keyBy('id');
            foreach ($ids as $index => $id) {
                $request = $requests->get($id);
                $country = $request->country ?? 'USA';
                if (! in_array($country, ['USA', 'Canada'], true)) {
                    throw ValidationException::withMessages(['codes' => 'Promo codes are only available for USA or Canada pass requests.']);
                }

                $code = PromoCodeRepository::whereKey($codes[$index])->lockForUpdate()->first();
                if ($code === null) {
                    $expiration = now()->startOfYear()->month(9)->day(1);
                    if (now()->month >= 9) {
                        $expiration->addYear();
                    }
                    $code = PromoCodeRepository::firstOrCreate(['promo_code' => $codes[$index]], [
                        'season_id' => $seasonId,
                        'start_date' => now()->toDateString(),
                        'expiration_date' => $expiration->toDateString(),
                        'country' => $country,
                        'is_suspended' => false,
                    ]);
                    $code = PromoCodeRepository::whereKey($code->promo_code)->lockForUpdate()->firstOrFail();
                }
                if ((int) $code->season_id !== $seasonId || ($code->country ?? 'USA') !== $country || $code->is_suspended) {
                    throw ValidationException::withMessages(['codes' => 'Each promo code must be active and belong to the selected season and passholder country.']);
                }
                if (PassRequest::where('promo_code', $code->promo_code)->where('id', '!=', $request->id)->exists()) {
                    throw ValidationException::withMessages(['codes' => 'A promo code is already assigned to another pass request.']);
                }

                $this->assign($request, $code->promo_code);
            }

            return count($ids);
        }, 3);
    }

    public function autoAssign(User $actor, int $seasonId): int
    {
        $this->authorize($actor);

        return DB::transaction(function () use ($seasonId) {
            Season::whereKey($seasonId)->lockForUpdate()->firstOrFail();
            $requests = PassRequest::where('season_id', $seasonId)->whereNull('promo_code')
                ->orderBy('created_at')->orderBy('id')->lockForUpdate()->get();
            $codes = PromoCodeRepository::where('season_id', $seasonId)
                ->where('is_suspended', false)
                ->whereDoesntHave('passRequests')
                ->orderBy('start_date')->orderBy('promo_code')
                ->lockForUpdate()->get()
                ->groupBy(fn (PromoCodeRepository $code) => $code->country ?? 'USA');

            $assigned = 0;
            foreach ($requests as $request) {
                $available = $codes->get($request->country ?? 'USA');
                if ($available === null || $available->isEmpty()) {
                    continue;
                }
                $this->assign($request, $available->shift()->promo_code);
                $assigned++;
            }

            return $assigned;
        }, 3);
    }

    public function clearCodes(User $actor, int $seasonId, array $data): int
    {
        $this->authorize($actor);
        $ids = $this->selectedIds($data);

        return DB::transaction(function () use ($seasonId, $ids) {
            $requests = $this->lockSelection($seasonId, $ids);

            return PassRequest::whereIn('id', $requests->modelKeys())->update([
                'promo_code' => null,
                'assign_code_date' => null,
                'email_notify_time' => null,
                'redemption_date' => null,
            ]);
        });
    }

    public function sendEmails(User $actor, int $seasonId, array $data): int
    {
        $this->authorize($actor);
        $ids = $this->selectedIds($data);
        $validated = Validator::make($data, ['force_send' => ['sometimes', 'boolean']])->validate();
        $season = Season::findOrFail($seasonId);
        DB::transaction(fn () => $this->lockSelection($seasonId, $ids));
        $forceSend = $validated['force_send'] ?? false;
        $sent = 0;

        foreach ($ids as $id) {
            try {
                // Recheck notification state under the same lock used by assignment.
                $sent += DB::transaction(function () use ($actor, $season, $id, $forceSend) {
                    $requests = $this->lockSelection($season->id, [$id]);
                    $passRequest = $requests->first();
                    if ($passRequest->promo_code === null || (! $forceSend && $passRequest->email_notify_time !== null)) {
                        return 0;
                    }
                    $toAddresses = array_values(array_unique([$passRequest->passholder_email, $actor->email]));
                    Mail::to($toAddresses)->send(new PassCodeNotification($passRequest, $season));
                    $passRequest->update(['email_notify_time' => now()]);
                    $this->logEmail($passRequest, $season, $toAddresses);

                    return 1;
                });
            } catch (ValidationException $exception) {
                throw $exception;
            } catch (\Exception $exception) {
                $passRequest = PassRequest::where('season_id', $seasonId)->findOrFail($id);
                $this->logEmail($passRequest, $season, [$passRequest->passholder_email], $exception->getMessage());
            }
        }

        return $sent;
    }

    private function authorize(User $actor): void
    {
        if (! $actor->isAdmin()) {
            throw new AuthorizationException('Administrator access is required.');
        }
    }

    private function filteredQuery(array $filters): Builder
    {
        foreach (['is_renewal', 'recent_only', 'has_promo_code'] as $name) {
            if (array_key_exists($name, $filters)) {
                $boolean = filter_var($filters[$name], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                if ($boolean !== null) {
                    $filters[$name] = $boolean;
                }
            }
        }
        $validated = Validator::make($filters, [
            'season_id' => ['sometimes', 'integer', 'exists:seasons,id'],
            'user_id' => ['sometimes', 'integer', 'exists:users,id'],
            'is_renewal' => ['sometimes', 'boolean'],
            'recent_only' => ['sometimes', 'boolean'],
            'has_promo_code' => ['sometimes', 'boolean'],
            'country' => ['sometimes', 'string', 'in:USA,Canada,Other'],
        ])->validate();
        $query = PassRequest::with(['user:id,first_name,last_name,email', 'seasonPassType', 'season']);
        foreach (['season_id', 'user_id', 'is_renewal'] as $field) {
            if (array_key_exists($field, $validated)) {
                $query->where($field, $validated[$field]);
            }
        }
        if ($validated['recent_only'] ?? false) {
            $query->where('assign_code_date', '>=', now()->subDay());
        }
        if (array_key_exists('has_promo_code', $validated)) {
            $validated['has_promo_code'] ? $query->whereNotNull('promo_code') : $query->whereNull('promo_code');
        }
        if (isset($validated['country'])) {
            $query->where(function (Builder $countryQuery) use ($validated) {
                $countryQuery->where('country', $validated['country']);
                if ($validated['country'] === 'USA') {
                    $countryQuery->orWhereNull('country');
                }
            });
        }

        return $query->orderByDesc('created_at')->orderByDesc('id');
    }

    private function selectedIds(array $data): array
    {
        $validated = Validator::make($data, [
            'pass_request_ids' => ['required', 'array', 'min:1', 'max:1000'],
            'pass_request_ids.*' => ['required', 'string', 'max:255', 'distinct:strict'],
        ])->validate();

        return array_values($validated['pass_request_ids']);
    }

    private function lockSelection(int $seasonId, array $ids): Collection
    {
        Season::whereKey($seasonId)->lockForUpdate()->firstOrFail();
        $requests = PassRequest::where('season_id', $seasonId)->whereIn('id', $ids)
            ->orderBy('id')->lockForUpdate()->get();
        if ($requests->count() !== count($ids)) {
            throw ValidationException::withMessages(['pass_request_ids' => 'Every selected pass request must belong to this season.']);
        }

        return $requests;
    }

    private function assign(PassRequest $request, string $code): void
    {
        $attributes = ['promo_code' => $code, 'assign_code_date' => now()];
        if ($request->promo_code !== $code) {
            $attributes += ['email_notify_time' => null, 'redemption_date' => null];
        }
        $request->update($attributes);
    }

    private function logEmail(PassRequest $request, Season $season, array $to, ?string $error = null): void
    {
        EmailLog::create([
            'event' => 'pass_code_notification',
            'email_to' => implode(', ', $to),
            'email_from' => config('mail.from.address', 'noreply@example.com'),
            'subject' => "Your {$season->pass_name} {$season->pass_year} Promo Code",
            'body' => $error === null ? "Promo code: {$request->promo_code}" : 'Failed: '.$error,
            'result' => $error === null ? 'sent' : 'failed',
            'error_message' => $error,
        ]);
    }
}
