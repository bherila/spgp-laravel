<?php

namespace App\Services\PassRequests;

use App\Http\Resources\PassRequestResource;
use App\Models\PassRequest;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Validator;

/**
 * Transport adapters share authorization, pagination, and response serialization.
 * Business validation and mutations live in the services also used by the website.
 */
class PassRequestOperations
{
    public function __construct(
        private UserPassRequestService $userRequests,
        private AdminPassRequestService $adminRequests,
    ) {}

    public static function definitions(bool $admin = false): array
    {
        $common = [
            'list_pass_requests' => 'List pass requests with bounded pagination. User access always lists only your own requests.',
            'get_pass_request' => 'Get one pass request and its assignment, notification, and redemption status.',
            'update_pass_request' => 'Update passholder contact details, birth date, or the renewal order number.',
            'list_seasons' => 'List seasons and available pass types.',
        ];

        return $admin ? $common + [
            'delete_pass_request' => 'Administratively delete a pass request. This can release its assigned promo code.',
            'bulk_delete_pass_requests' => 'Delete selected requests in one season. Assigned promo codes must be cleared first.',
            'assign_promo_codes' => 'Assign one newline-separated promo code per selected request, in selection order.',
            'auto_assign_promo_codes' => 'Assign available repository codes to pending requests in a season, matching country.',
            'clear_promo_codes' => 'Clear selected promo codes and their notification and redemption state.',
            'send_pass_code_emails' => 'Email assigned pass codes. Already-notified requests are skipped unless force_send is true.',
        ] : $common + [
            'create_pass_request' => 'Create a pass request for a passholder under your SPGP account.',
            'cancel_pass_request' => 'Cancel your own pass request before a promo code has been assigned.',
            'set_renewal_order' => 'Record your renewal order number and its reporting date.',
            'remove_renewal_order' => 'Remove your renewal order number and its reporting date.',
            'set_missing_country' => 'Set USA or Canada on your requests missing a country. Canada releases previously assigned USA codes.',
            'list_pass_types' => 'List pass types and prices for a season.',
        ];
    }

    public static function scope(string $operation, bool $admin = false): string
    {
        $read = in_array($operation, ['list_pass_requests', 'get_pass_request', 'list_seasons', 'list_pass_types'], true);

        return ($admin ? 'admin:' : '').'pass-requests:'.($read ? 'read' : 'write');
    }

    public function execute(string $operation, array $input = [], bool $admin = false): array
    {
        abort_unless(array_key_exists($operation, self::definitions($admin)), 404);
        $actor = ApiAccess::authorize(self::scope($operation, $admin));
        if ($admin && ! $actor->isAdmin()) {
            throw new AuthorizationException('Administrator access is required.');
        }

        $needsId = in_array($operation, ['get_pass_request', 'update_pass_request', 'cancel_pass_request', 'delete_pass_request', 'set_renewal_order', 'remove_renewal_order'], true);
        $validated = Validator::make($input, [
            'id' => [$needsId ? 'required' : 'sometimes', 'string', 'max:255'],
            'season_id' => ['sometimes', 'integer', 'min:1'],
        ])->validate();
        $id = $validated['id'] ?? '';

        switch ($operation) {
            case 'list_pass_requests':
                if ($admin) {
                    return $this->page($this->adminRequests->query($actor, $input));
                }
                $pagination = Validator::make($input, [
                    'page' => ['sometimes', 'integer', 'min:1'],
                    'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
                ])->validate();

                return $this->page($this->userRequests->query($actor, $input)->paginate(
                    $pagination['per_page'] ?? 25, ['*'], 'page', $pagination['page'] ?? 1,
                ));
            case 'get_pass_request':
                return $this->record($admin ? $this->adminRequests->get($actor, $id) : $this->userRequests->get($actor, $id));
            case 'create_pass_request':
                return $this->record($this->userRequests->create($actor, $input));
            case 'update_pass_request':
                return $this->record($admin ? $this->adminRequests->update($actor, $id, $input) : $this->userRequests->update($actor, $id, $input));
            case 'cancel_pass_request':
                $this->userRequests->cancel($actor, $id);

                return ['message' => 'Pass request cancelled successfully.'];
            case 'delete_pass_request':
                $this->adminRequests->delete($actor, $id);

                return ['message' => 'Pass request deleted successfully.'];
            case 'set_renewal_order':
                return $this->record($this->userRequests->setRenewalOrder($actor, $id, $input));
            case 'remove_renewal_order':
                return $this->record($this->userRequests->removeRenewalOrder($actor, $id));
            case 'set_missing_country':
                return ['updated' => $this->userRequests->updateCountry($actor, $input)];
            case 'list_seasons':
                return ['data' => ($admin ? $this->adminRequests->seasons($actor, $input) : $this->userRequests->activeSeasons($actor))->toArray()];
            case 'list_pass_types':
                $seasonId = $this->seasonId($input);

                return ['data' => $this->userRequests->passTypes($seasonId)->toArray()];
            case 'bulk_delete_pass_requests':
                return ['deleted' => $this->adminRequests->bulkDelete($actor, $this->seasonId($input), $input)];
            case 'assign_promo_codes':
                return ['assigned' => $this->adminRequests->assignCodes($actor, $this->seasonId($input), $input)];
            case 'auto_assign_promo_codes':
                return ['assigned' => $this->adminRequests->autoAssign($actor, $this->seasonId($input))];
            case 'clear_promo_codes':
                return ['cleared' => $this->adminRequests->clearCodes($actor, $this->seasonId($input), $input)];
            case 'send_pass_code_emails':
                return ['sent' => $this->adminRequests->sendEmails($actor, $this->seasonId($input), $input)];
        }

        abort(404);
    }

    private function seasonId(array $input): int
    {
        return (int) Validator::make($input, ['season_id' => ['required', 'integer', 'min:1']])->validate()['season_id'];
    }

    private function record(PassRequest $passRequest): array
    {
        $passRequest->loadMissing(['season', 'seasonPassType']);

        return ['data' => (new PassRequestResource($passRequest))->resolve()];
    }

    private function page(LengthAwarePaginator $page): array
    {
        return [
            'data' => PassRequestResource::collection($page->items())->resolve(),
            'meta' => [
                'current_page' => $page->currentPage(), 'per_page' => $page->perPage(),
                'last_page' => $page->lastPage(), 'total' => $page->total(),
            ],
        ];
    }
}
