<?php

namespace Tests\Feature;

use App\Models\Season;
use App\Models\SeasonPassType;
use App\Models\User;
use App\Services\PassRequests\UserPassRequestService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class UserPassRequestServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_requires_a_pass_type_from_the_selected_season(): void
    {
        $actor = User::factory()->create();
        $input = $this->input();
        $unrelatedType = SeasonPassType::factory()->create();

        try {
            $this->service()->create($actor, [...$input, 'season_pass_type_id' => $unrelatedType->id]);
            $this->fail('A pass type from another season must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('season_pass_type_id', $exception->errors());
            $this->assertDatabaseCount('pass_requests', 0);
        }
    }

    public function test_create_and_update_ignore_privileged_fields(): void
    {
        $actor = User::factory()->create();
        $other = User::factory()->create();
        $input = $this->input();
        $record = $this->service()->create($actor, [
            ...$input,
            'user_id' => $other->id,
            'promo_code' => 'INJECTED',
            'assign_code_date' => '2026-01-01',
            'email_notify_time' => '2026-01-01',
            'redemption_date' => '2026-01-01',
            'pass_type' => 'Forged pass type',
        ]);

        $this->assertSame($actor->id, $record->user_id);
        $this->assertSame(SeasonPassType::findOrFail($input['season_pass_type_id'])->pass_type_name, $record->pass_type);
        $this->assertNull($record->promo_code);
        $this->assertNull($record->assign_code_date);
        $this->assertNull($record->email_notify_time);
        $this->assertNull($record->redemption_date);

        $this->service()->update($actor, $record->id, [
            'passholder_first_name' => 'Updated',
            'user_id' => $other->id,
            'season_id' => Season::factory()->create()->id,
            'promo_code' => 'INJECTED',
            'country' => 'Canada',
        ]);

        $record->refresh();
        $this->assertSame('Updated', $record->passholder_first_name);
        $this->assertSame($actor->id, $record->user_id);
        $this->assertSame($input['season_id'], $record->season_id);
        $this->assertNull($record->promo_code);
        $this->assertSame('USA', $record->country);
    }

    public function test_update_rejects_a_duplicate_identity_after_merging_partial_changes(): void
    {
        $actor = User::factory()->create();
        $input = $this->input();
        $this->service()->create($actor, $input);
        $second = $this->service()->create($actor, [...$input, 'passholder_first_name' => 'Different']);

        try {
            $this->service()->update($actor, $second->id, ['passholder_first_name' => $input['passholder_first_name']]);
            $this->fail('An edit must not create a duplicate identity.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('passholder_email', $exception->errors());
            $this->assertSame('Different', $second->fresh()->passholder_first_name);
        }

        $this->assertSame('Different', $this->service()->update($actor, $second->id, ['renewal_order_number' => 'ORDER-123'])->passholder_first_name);
    }

    public function test_birth_date_format_cannot_bypass_duplicate_detection(): void
    {
        $actor = User::factory()->create();
        $input = $this->input();
        $this->service()->create($actor, $input);

        $this->expectException(ValidationException::class);
        $this->service()->create($actor, [...$input, 'passholder_birth_date' => '1990-01-01T00:00:00+00:00']);
    }

    #[DataProvider('genericRenewalOrderTransitions')]
    public function test_generic_renewal_order_edits_keep_the_reporting_date_in_sync(
        bool $administrative,
        ?string $originalNumber,
        ?string $originalDate,
        array $changes,
        ?string $expectedNumber,
        ?string $expectedDate,
    ): void {
        $this->travelTo(now()->setDate(2026, 10, 23)->setTime(12, 0));
        $owner = User::factory()->create();
        $actor = $administrative ? User::factory()->admin()->create() : $owner;
        $record = $this->service()->create($owner, $this->input());
        $record->update(['renewal_order_number' => $originalNumber, 'assign_code_date' => $originalDate]);

        $updated = $administrative
            ? $this->service()->updateForAdministration($actor, $record->id, $changes)
            : $this->service()->update($actor, $record->id, $changes);

        $this->assertSame($expectedNumber, $updated->renewal_order_number);
        $this->assertSame($expectedDate, $updated->assign_code_date?->toDateString());
        $this->assertSame($expectedNumber, $record->fresh()->renewal_order_number);
        $this->assertSame($expectedDate, $record->fresh()->assign_code_date?->toDateString());
    }

    public static function genericRenewalOrderTransitions(): array
    {
        $transitions = [
            'set' => [null, null, ['renewal_order_number' => 'ORDER-123'], 'ORDER-123', '2026-10-23'],
            'replace' => ['ORDER-123', '2026-10-20', ['renewal_order_number' => 'ORDER-456'], 'ORDER-456', '2026-10-23'],
            'clear' => ['ORDER-123', '2026-10-20', ['renewal_order_number' => null], null, null],
            'empty string clears' => ['ORDER-123', '2026-10-20', ['renewal_order_number' => ''], null, null],
            'unchanged' => ['ORDER-123', '2026-10-20', ['renewal_order_number' => 'ORDER-123'], 'ORDER-123', '2026-10-20'],
            'backfill missing date' => ['ORDER-123', null, ['renewal_order_number' => 'ORDER-123'], 'ORDER-123', '2026-10-23'],
            'profile only' => ['ORDER-123', '2026-10-20', ['passholder_first_name' => 'Updated'], 'ORDER-123', '2026-10-20'],
            'profile preserves assigned code date' => [null, '2026-10-20', ['passholder_first_name' => 'Updated'], null, '2026-10-20'],
            'unchanged null preserves assigned code date' => [null, '2026-10-20', ['renewal_order_number' => null], null, '2026-10-20'],
        ];
        $cases = [];
        foreach ([false, true] as $administrative) {
            foreach ($transitions as $name => $transition) {
                $cases[($administrative ? 'admin ' : 'owner ').$name] = [$administrative, ...$transition];
            }
        }

        return $cases;
    }

    public function test_dedicated_renewal_order_actions_share_the_reporting_date_transition_rules(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 20)->setTime(12, 0));
        $actor = User::factory()->create();
        $record = $this->service()->create($actor, $this->input());

        $this->service()->setRenewalOrder($actor, $record->id, ['renewal_order_number' => 'ORDER-123']);
        $this->assertSame('2026-10-20', $record->fresh()->assign_code_date->toDateString());
        $this->travelTo(now()->setDate(2026, 10, 23)->setTime(12, 0));
        $this->service()->setRenewalOrder($actor, $record->id, ['renewal_order_number' => 'ORDER-123']);
        $this->assertSame('2026-10-20', $record->fresh()->assign_code_date->toDateString());

        $this->service()->setRenewalOrder($actor, $record->id, ['renewal_order_number' => 'ORDER-456']);
        $this->assertSame('ORDER-456', $record->fresh()->renewal_order_number);
        $this->assertSame('2026-10-23', $record->fresh()->assign_code_date->toDateString());
        $record->refresh()->update(['assign_code_date' => null]);
        $this->service()->setRenewalOrder($actor, $record->id, ['renewal_order_number' => 'ORDER-456']);
        $this->assertSame('2026-10-23', $record->fresh()->assign_code_date->toDateString());

        $this->service()->removeRenewalOrder($actor, $record->id);
        $this->assertNull($record->fresh()->renewal_order_number);
        $this->assertNull($record->fresh()->assign_code_date);
    }

    public function test_clearing_an_absent_renewal_order_preserves_the_promo_code_assignment_date(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 23)->setTime(12, 0));
        $actor = User::factory()->create();
        $record = $this->service()->create($actor, $this->input());
        $record->update(['promo_code' => 'ASSIGNED', 'assign_code_date' => '2026-10-20']);

        $this->service()->update($actor, $record->id, ['renewal_order_number' => null, 'passholder_first_name' => 'Updated']);
        $this->assertSame('2026-10-20', $record->fresh()->assign_code_date->toDateString());
        $this->service()->removeRenewalOrder($actor, $record->id);
        $record->refresh();

        $this->assertNull($record->renewal_order_number);
        $this->assertSame('ASSIGNED', $record->promo_code);
        $this->assertSame('2026-10-20', $record->assign_code_date->toDateString());
    }

    #[DataProvider('ownerOnlyOperations')]
    public function test_administrators_cannot_access_other_users_requests_through_user_operations(string $operation): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $record = $this->service()->create($owner, $this->input());

        $this->expectException(AuthorizationException::class);

        match ($operation) {
            'get' => $this->service()->get($admin, $record->id),
            'update' => $this->service()->update($admin, $record->id, ['passholder_first_name' => 'Changed']),
            'renewal' => $this->service()->setRenewalOrder($admin, $record->id, ['renewal_order_number' => 'ORDER-123']),
            'remove_renewal' => $this->service()->removeRenewalOrder($admin, $record->id),
            'cancel' => $this->service()->cancel($admin, $record->id),
        };
    }

    public static function ownerOnlyOperations(): array
    {
        return [
            'get' => ['get'],
            'update' => ['update'],
            'set renewal order' => ['renewal'],
            'remove renewal order' => ['remove_renewal'],
            'cancel' => ['cancel'],
        ];
    }

    public function test_user_query_and_grouped_list_stay_owner_scoped_for_administrators(): void
    {
        $admin = User::factory()->admin()->create();
        $other = User::factory()->create();
        $input = $this->input();
        $own = $this->service()->create($admin, $input);
        $this->service()->create($other, [...$input, 'passholder_first_name' => 'Other']);

        $this->assertSame([$own->id], $this->service()->query($admin, ['user_id' => $other->id])->pluck('id')->all());
        $this->assertSame([$own->id], $this->service()->grouped($admin)->first()->passRequests->pluck('id')->all());
        $this->assertFalse($this->service()->get($admin, $own->id)->relationLoaded('user'));
    }

    public function test_administrative_update_requires_an_administrator(): void
    {
        $owner = User::factory()->create();
        $record = $this->service()->create($owner, $this->input());

        $this->expectException(AuthorizationException::class);
        $this->service()->updateForAdministration($owner, $record->id, ['passholder_first_name' => 'Changed']);
    }

    public function test_browser_update_preserves_admin_permission_and_unauthorized_message(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $owner = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $other = User::factory()->create();
        $record = $this->service()->create($owner, $this->input());

        $this->actingAs($other)->putJson('/api/pass-requests/'.$record->id, ['passholder_first_name' => 'Changed'])
            ->assertForbidden()->assertJsonPath('message', 'Unauthorized');
        $this->actingAs($admin)->putJson('/api/pass-requests/'.$record->id, ['passholder_first_name' => 'Changed'])
            ->assertOk()->assertJsonPath('passholder_first_name', 'Changed');
    }

    public function test_assigned_code_prevents_owner_cancellation(): void
    {
        $actor = User::factory()->create();
        $record = $this->service()->create($actor, $this->input());
        $record->update(['promo_code' => 'ASSIGNED']);

        try {
            $this->service()->cancel($actor, $record->id);
            $this->fail('An assigned request must not be cancelled.');
        } catch (ValidationException $exception) {
            $this->assertSame('This pass request cannot be cancelled because a promo code has already been assigned.', $exception->getMessage());
            $this->assertModelExists($record);
        }
    }

    public function test_country_update_only_changes_the_actors_missing_countries_and_releases_wrong_country_codes(): void
    {
        $actor = User::factory()->create();
        $other = User::factory()->create();
        $input = $this->input();
        $own = $this->service()->create($actor, $input);
        $own->update(['country' => null, 'promo_code' => 'USA-CODE', 'assign_code_date' => now(), 'email_notify_time' => now()]);
        $foreign = $this->service()->create($other, [...$input, 'passholder_first_name' => 'Other']);
        $foreign->update(['country' => null]);

        $this->assertSame(1, $this->service()->updateCountry($actor, ['country' => 'Canada']));
        $own->refresh();
        $this->assertSame('Canada', $own->country);
        $this->assertNull($own->promo_code);
        $this->assertNull($own->assign_code_date);
        $this->assertNull($own->email_notify_time);
        $this->assertNull($foreign->fresh()->country);
    }

    private function service(): UserPassRequestService
    {
        return $this->app->make(UserPassRequestService::class);
    }

    private function input(): array
    {
        $type = SeasonPassType::factory()->create();

        return [
            'season_id' => $type->season_id,
            'season_pass_type_id' => $type->id,
            'passholder_first_name' => 'John',
            'passholder_last_name' => 'Doe',
            'passholder_email' => 'john@example.test',
            'passholder_birth_date' => '1990-01-01',
            'country' => 'USA',
        ];
    }
}
