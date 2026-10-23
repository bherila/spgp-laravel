<?php

namespace Tests\Feature;

use App\Models\SeasonPassType;
use App\Models\User;
use App\Services\PassRequests\UserPassRequestService;
use BWH\Auth\OAuth\Introspection\IntrospectedToken;
use BWH\Auth\OAuth\Introspection\OAuthTokenIntrospector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class PassRequestMcpTest extends TestCase
{
    use RefreshDatabase;

    private const ISSUER = 'https://identity.example.test';

    private const RESOURCE = 'https://passes.example.test';

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'pass-request-api.enabled' => true,
            'bherila-auth.oauth_resource_server.issuer' => self::ISSUER,
            'bherila-auth.oauth_resource_server.resource' => self::RESOURCE,
        ]);
    }

    public function test_mcp_rejects_cookie_auth_and_preserves_external_oauth_discovery(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $this->postJson('/mcp/user', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'])
            ->assertUnauthorized()
            ->assertHeader('WWW-Authenticate', 'Bearer resource_metadata="'.self::RESOURCE.'/.well-known/oauth-protected-resource"');
        $this->getJson('/.well-known/oauth-protected-resource')->assertOk()
            ->assertJsonPath('resource', self::RESOURCE)
            ->assertJsonPath('authorization_servers.0', self::ISSUER)
            ->assertJsonPath('bearer_methods_supported.0', 'header');
    }

    public function test_user_and_admin_servers_have_distinct_scope_filtered_tools(): void
    {
        $this->principal(['pass-requests:read', 'admin:pass-requests:read'], true);
        $userTools = $this->rpc('/mcp/user', 'tools/list')->assertOk()->json('result.tools');
        $names = array_column($userTools, 'name');
        $this->assertContains('list_pass_requests', $names);
        $this->assertNotContains('create_pass_request', $names);
        $this->assertNotContains('assign_promo_codes', $names);

        $adminTools = $this->rpc('/mcp/admin', 'tools/list')->assertOk()->json('result.tools');
        $this->assertContains('get_pass_request', array_column($adminTools, 'name'));
        $this->assertNotContains('create_pass_request', array_column($adminTools, 'name'));
        $this->assertNotContains('delete_pass_request', array_column($adminTools, 'name'));

        $list = collect($userTools)->firstWhere('name', 'list_pass_requests');
        $this->assertNotContains('season_id', $list['inputSchema']['required'] ?? []);
        $this->assertTrue($list['annotations']['readOnlyHint']);
    }

    public function test_a_hidden_write_tool_cannot_be_invoked_with_read_only_scope(): void
    {
        $this->principal(['pass-requests:read']);
        $this->rpc('/mcp/user', 'tools/call', ['name' => 'create_pass_request', 'arguments' => $this->input()])
            ->assertStatus(400)->assertJsonPath('error.code', -32602);
        $this->assertDatabaseCount('pass_requests', 0);
    }

    public function test_tools_create_and_update_the_same_records_as_the_rest_api(): void
    {
        $owner = $this->principal(['pass-requests:read', 'pass-requests:write']);
        $response = $this->rpc('/mcp/user', 'tools/call', ['name' => 'create_pass_request', 'arguments' => $this->input()])
            ->assertOk()->assertJsonPath('result.isError', false)
            ->assertJsonPath('result.structuredContent.data.user_id', $owner->id);
        $id = $response->json('result.structuredContent.data.id');
        $this->getJson('/api/v1/pass-requests/'.$id, ['Authorization' => 'Bearer mcp-token'])
            ->assertOk()->assertJsonPath('data.id', $id);
        $this->rpc('/mcp/user', 'tools/call', [
            'name' => 'update_pass_request',
            'arguments' => ['id' => $id, 'passholder_first_name' => 'Updated'],
        ])->assertOk()->assertJsonPath('result.structuredContent.data.passholder_first_name', 'Updated');
        $this->assertDatabaseHas('pass_requests', ['id' => $id, 'passholder_first_name' => 'Updated']);
    }

    public function test_tool_validation_errors_are_returned_without_creating_records(): void
    {
        $this->principal(['pass-requests:write']);
        $input = $this->input();
        $input['season_pass_type_id'] = SeasonPassType::factory()->create()->id;
        $this->rpc('/mcp/user', 'tools/call', ['name' => 'create_pass_request', 'arguments' => $input])
            ->assertOk()->assertJsonPath('result.isError', true);
        $this->assertDatabaseCount('pass_requests', 0);
    }

    public function test_user_tools_do_not_allow_an_admin_to_change_another_users_request(): void
    {
        $owner = User::factory()->create();
        $record = app(UserPassRequestService::class)->create($owner, $this->input());
        $this->principal(['pass-requests:write', 'admin:pass-requests:write'], true);
        $this->rpc('/mcp/user', 'tools/call', [
            'name' => 'update_pass_request',
            'arguments' => ['id' => $record->id, 'passholder_first_name' => 'Unauthorized'],
        ])->assertOk()->assertJsonPath('result.isError', true);
        $this->assertNotSame('Unauthorized', $record->fresh()->passholder_first_name);

        $this->rpc('/mcp/admin', 'tools/call', [
            'name' => 'update_pass_request',
            'arguments' => ['id' => $record->id, 'passholder_first_name' => 'Authorized'],
        ])->assertOk()->assertJsonPath('result.structuredContent.data.passholder_first_name', 'Authorized');
    }

    public function test_admin_server_rejects_a_member_even_with_admin_scopes(): void
    {
        $this->principal(['admin:pass-requests:read', 'admin:pass-requests:write']);
        $this->rpc('/mcp/admin', 'tools/list')->assertForbidden();
    }

    public function test_origin_validation_does_not_trust_the_request_host(): void
    {
        $this->principal(['pass-requests:read']);
        $this->rpc('/mcp/user', 'tools/list', [], ['Origin' => 'https://attacker.example.test'])
            ->assertForbidden();
        $this->rpc('/mcp/user', 'tools/list', [], ['Origin' => self::RESOURCE])->assertOk();
    }

    public function test_initialize_and_notifications_use_the_standard_http_protocol(): void
    {
        $this->principal(['pass-requests:read']);
        $this->rpc('/mcp/user', 'initialize', [
            'protocolVersion' => '2025-11-25',
            'capabilities' => (object) [],
            'clientInfo' => ['name' => 'integration-test', 'version' => '1.0'],
        ])->assertOk()->assertJsonPath('result.protocolVersion', '2025-11-25')
            ->assertJsonPath('result.serverInfo.name', 'SPGP Pass Requests');
        $this->postJson('/mcp/user', ['jsonrpc' => '2.0', 'method' => 'notifications/initialized'], ['Authorization' => 'Bearer mcp-token'])
            ->assertStatus(202);
    }

    private function principal(array $scopes, bool $admin = false): User
    {
        $user = User::factory()->create(['is_admin' => $admin]);
        $subject = 'subject-'.$user->id;
        $user->forceFill(['oauth_provider' => self::ISSUER, 'oauth_subject' => $subject])->save();
        $token = new IntrospectedToken(
            active: true, issuer: self::ISSUER, subject: $subject, clientId: 'mcp-client',
            scopes: $scopes, audiences: [self::RESOURCE], resource: self::RESOURCE,
            expiresAt: now()->addHour()->getTimestamp(),
        );
        $this->mock(OAuthTokenIntrospector::class)->shouldReceive('introspect')->with('mcp-token')->andReturn($token);

        return $user;
    }

    private function rpc(string $path, string $method, array $params = [], array $headers = []): TestResponse
    {
        return $this->postJson($path, ['jsonrpc' => '2.0', 'id' => 1, 'method' => $method, 'params' => $params], [
            'Authorization' => 'Bearer mcp-token', 'MCP-Protocol-Version' => '2025-11-25', ...$headers,
        ]);
    }

    private function input(): array
    {
        $type = SeasonPassType::factory()->create();

        return [
            'season_id' => $type->season_id, 'season_pass_type_id' => $type->id,
            'passholder_first_name' => 'Test', 'passholder_last_name' => 'Passholder',
            'passholder_email' => 'test@example.com', 'passholder_birth_date' => '1990-01-01',
            'country' => 'USA',
        ];
    }
}
