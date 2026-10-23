<?php

namespace App\Mcp\Tools;

use App\Services\PassRequests\ApiAccess;
use App\Services\PassRequests\PassRequestOperations;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;

class PassRequestTool extends Tool
{
    public function __construct(private readonly string $operation, private readonly bool $admin = false)
    {
        $this->name = $operation;
        $this->description = PassRequestOperations::definitions($admin)[$operation];
    }

    public function shouldRegister(): bool
    {
        return ApiAccess::allows(PassRequestOperations::scope($this->operation, $this->admin))
            && (! $this->admin || ApiAccess::user()->isAdmin());
    }

    public function handle(Request $request, PassRequestOperations $operations): Response|ResponseFactory
    {
        try {
            return Response::structured($operations->execute($this->operation, $request->all(), $this->admin));
        } catch (ModelNotFoundException) {
            return Response::error('Pass request or season not found.');
        }
    }

    public function annotations(): array
    {
        return [
            'readOnlyHint' => str_ends_with(PassRequestOperations::scope($this->operation, $this->admin), ':read'),
            'destructiveHint' => in_array($this->operation, ['update_pass_request', 'cancel_pass_request', 'delete_pass_request', 'bulk_delete_pass_requests', 'assign_promo_codes', 'clear_promo_codes', 'set_renewal_order', 'remove_renewal_order', 'set_missing_country', 'send_pass_code_emails'], true),
            'idempotentHint' => in_array($this->operation, ['list_pass_requests', 'get_pass_request', 'list_seasons', 'list_pass_types', 'update_pass_request', 'clear_promo_codes', 'remove_renewal_order'], true),
            'openWorldHint' => $this->operation === 'send_pass_code_emails',
        ];
    }

    public function schema(JsonSchema $schema): array
    {
        $id = $schema->string()->max(255)->description('SPGP pass request ID.');
        $season = $schema->integer()->min(1)->description('Season ID from list_seasons.');
        $pagination = ['page' => $schema->integer()->min(1), 'per_page' => $schema->integer()->min(1)->max(100)];
        $passholder = [
            'passholder_first_name' => $schema->string()->max(255),
            'passholder_last_name' => $schema->string()->max(255),
            'passholder_email' => $schema->string()->format('email')->max(255),
            'passholder_birth_date' => $schema->string()->format('date')->description('Birth date as YYYY-MM-DD.'),
        ];
        $selection = [
            'season_id' => (clone $season)->required(),
            'pass_request_ids' => $schema->array()->items($schema->string()->max(255))->min(1)->max(1000)->unique()->required(),
        ];

        return match ($this->operation) {
            'list_pass_requests' => $pagination + ['season_id' => $season] + ($this->admin ? [
                'user_id' => $schema->integer()->min(1), 'is_renewal' => $schema->boolean(),
                'has_promo_code' => $schema->boolean(), 'recent_only' => $schema->boolean(),
                'country' => $schema->string()->enum(['USA', 'Canada', 'Other']),
            ] : []),
            'get_pass_request', 'cancel_pass_request', 'delete_pass_request', 'remove_renewal_order' => ['id' => $id->required()],
            'create_pass_request' => array_map(fn ($field) => $field->required(), $passholder) + [
                'season_id' => $season->required(), 'season_pass_type_id' => $schema->integer()->min(1)->required(),
                'country' => $schema->string()->enum(['USA', 'Canada', 'Other'])->required(),
                'is_renewal' => $schema->boolean(), 'renewal_pass_id' => $schema->string()->max(255)->nullable(),
            ],
            'update_pass_request' => ['id' => $id->required()] + $passholder + ['renewal_order_number' => $schema->string()->max(30)->nullable()],
            'set_renewal_order' => ['id' => $id->required(), 'renewal_order_number' => $schema->string()->max(30)->required()],
            'set_missing_country' => ['country' => $schema->string()->enum(['USA', 'Canada'])->required()],
            'list_seasons' => $this->admin ? ['include_archived' => $schema->boolean()] : [],
            'list_pass_types', 'auto_assign_promo_codes' => ['season_id' => $season->required()],
            'assign_promo_codes' => $selection + ['codes' => $schema->string()->description('One promo code per line, matching pass_request_ids order.')->required()],
            'clear_promo_codes', 'bulk_delete_pass_requests' => $selection,
            'send_pass_code_emails' => $selection + ['force_send' => $schema->boolean()],
            default => [],
        };
    }
}
