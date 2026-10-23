<?php

namespace Tests\Feature;

use App\Mail\PassCodeNotification;
use App\Models\PassRequest;
use App\Models\PromoCodeRepository;
use App\Models\Season;
use App\Models\SeasonPassType;
use App\Models\User;
use App\Services\PassRequests\AdminPassRequestService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AdminPassRequestServiceTest extends TestCase
{
    use RefreshDatabase;

    private AdminPassRequestService $service;

    private User $admin;

    private User $owner;

    private Season $season;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(AdminPassRequestService::class);
        $this->admin = User::factory()->admin()->create();
        $this->owner = User::factory()->create();
        $this->season = Season::factory()->create();
        Mail::fake();
    }

    public static function adminOperations(): array
    {
        return [
            'seasons' => ['seasons', []],
            'season listing' => ['listForSeason', [1]],
            'paginated listing' => ['query', []],
            'get' => ['get', ['missing']],
            'update' => ['update', ['missing', []]],
            'delete' => ['delete', ['missing']],
            'bulk delete' => ['bulkDelete', [1, []]],
            'manual assignment' => ['assignCodes', [1, []]],
            'automatic assignment' => ['autoAssign', [1]],
            'clear codes' => ['clearCodes', [1, []]],
            'send emails' => ['sendEmails', [1, []]],
        ];
    }

    #[DataProvider('adminOperations')]
    public function test_every_operation_requires_an_admin_inside_the_service(string $method, array $arguments): void
    {
        $this->expectException(AuthorizationException::class);
        $this->service->{$method}($this->owner, ...$arguments);
    }

    public function test_season_discovery_supports_archived_seasons_and_request_counts(): void
    {
        $this->passRequest();
        $archived = Season::factory()->create();
        $archived->delete();

        $seasons = $this->service->seasons($this->admin);
        $this->assertSame([$this->season->id], $seasons->modelKeys());
        $this->assertSame(1, $seasons->first()->pass_request_count);
        $this->assertCount(2, $this->service->seasons($this->admin, ['include_archived' => 'true']));
    }

    public function test_administrative_update_uses_shared_validation_and_ignores_privileged_fields(): void
    {
        $request = $this->passRequest();
        $updated = $this->service->update($this->admin, $request->id, [
            'passholder_first_name' => 'Updated',
            'promo_code' => 'INJECTED',
            'user_id' => $this->admin->id,
        ]);

        $this->assertSame('Updated', $updated->passholder_first_name);
        $this->assertSame($this->owner->id, $updated->user_id);
        $this->assertNull($updated->promo_code);
    }

    public function test_manual_assignment_preserves_input_order_and_ignores_blank_lines(): void
    {
        $first = $this->passRequest(['country' => null]);
        $second = $this->passRequest(['country' => 'Canada']);

        $assigned = $this->service->assignCodes($this->admin, $this->season->id, [
            'pass_request_ids' => ['canada' => $second->id, 'usa' => $first->id],
            'codes' => "\n CANADA-CODE \r\n\n USA-CODE \n",
        ]);

        $this->assertSame(2, $assigned);
        $this->assertSame('USA-CODE', $first->fresh()->promo_code);
        $this->assertSame('CANADA-CODE', $second->fresh()->promo_code);
        $this->assertNull($first->fresh()->country);
        $this->assertSame(now()->toDateString(), $first->fresh()->assign_code_date->toDateString());
        $this->assertDatabaseHas('promo_code_repository', ['promo_code' => 'CANADA-CODE', 'country' => 'Canada']);
        $this->assertDatabaseHas('promo_code_repository', ['promo_code' => 'USA-CODE', 'country' => 'USA']);
    }

    public function test_assignment_is_atomic_when_a_code_is_for_another_season(): void
    {
        $first = $this->passRequest();
        $second = $this->passRequest();
        $this->code('WRONG-SEASON', ['season_id' => Season::factory()->create()->id]);

        try {
            $this->service->assignCodes($this->admin, $this->season->id, [
                'pass_request_ids' => [$first->id, $second->id],
                'codes' => "NEW-CODE\nWRONG-SEASON",
            ]);
            $this->fail('A code from another season must be rejected.');
        } catch (ValidationException) {
            $this->assertNull($first->fresh()->promo_code);
            $this->assertNull($second->fresh()->promo_code);
            $this->assertDatabaseMissing('promo_code_repository', ['promo_code' => 'NEW-CODE']);
        }
    }

    public static function unavailableCodes(): array
    {
        return [
            'wrong country' => [['country' => 'Canada'], false],
            'suspended' => [['is_suspended' => true], false],
            'already assigned' => [[], true],
        ];
    }

    #[DataProvider('unavailableCodes')]
    public function test_unavailable_codes_cannot_be_assigned(array $attributes, bool $assigned): void
    {
        $request = $this->passRequest();
        $this->code('UNAVAILABLE', $attributes);
        if ($assigned) {
            $this->passRequest(['promo_code' => 'UNAVAILABLE']);
        }

        $this->expectException(ValidationException::class);
        $this->service->assignCodes($this->admin, $this->season->id, [
            'pass_request_ids' => [$request->id],
            'codes' => 'UNAVAILABLE',
        ]);
    }

    public function test_duplicate_codes_cannot_be_assigned_to_multiple_requests(): void
    {
        $first = $this->passRequest();
        $second = $this->passRequest();
        $this->expectException(ValidationException::class);

        $this->service->assignCodes($this->admin, $this->season->id, [
            'pass_request_ids' => [$first->id, $second->id],
            'codes' => "DUPLICATE\nDUPLICATE",
        ]);
    }

    public function test_selection_from_another_season_is_rejected_before_mutating(): void
    {
        $first = $this->passRequest();
        $otherSeason = $this->passRequest(['season_id' => Season::factory()->create()->id]);
        $this->expectException(ValidationException::class);

        $this->service->assignCodes($this->admin, $this->season->id, [
            'pass_request_ids' => [$first->id, $otherSeason->id],
            'codes' => "FIRST\nSECOND",
        ]);
    }

    public function test_automatic_assignment_treats_legacy_null_country_codes_as_usa_and_is_not_repeated(): void
    {
        $usa = $this->passRequest(['country' => null]);
        $canada = $this->passRequest(['country' => 'Canada']);
        $this->code('LEGACY-USA', ['country' => null]);
        $this->code('CANADA', ['country' => 'Canada']);
        $this->code('SUSPENDED', ['is_suspended' => true]);

        $this->assertSame(2, $this->service->autoAssign($this->admin, $this->season->id));
        $this->assertSame(0, $this->service->autoAssign($this->admin, $this->season->id));
        $this->assertSame('LEGACY-USA', $usa->fresh()->promo_code);
        $this->assertSame('CANADA', $canada->fresh()->promo_code);
        $this->assertNull($usa->fresh()->country);
    }

    public function test_replacing_or_clearing_codes_resets_notification_and_redemption_state(): void
    {
        $request = $this->passRequest([
            'promo_code' => 'OLD',
            'email_notify_time' => now(),
            'redemption_date' => now(),
        ]);
        $this->service->assignCodes($this->admin, $this->season->id, [
            'pass_request_ids' => [$request->id], 'codes' => 'NEW',
        ]);
        $this->assertNull($request->fresh()->email_notify_time);
        $this->assertNull($request->fresh()->redemption_date);

        $this->assertSame(1, $this->service->clearCodes($this->admin, $this->season->id, ['pass_request_ids' => [$request->id]]));
        $this->assertNull($request->fresh()->promo_code);
        $this->assertNull($request->fresh()->assign_code_date);
        $this->assertDatabaseHas('promo_code_repository', ['promo_code' => 'NEW']);
    }

    public function test_bulk_delete_rejects_assigned_requests_without_deleting_any_selection(): void
    {
        $free = $this->passRequest();
        $assigned = $this->passRequest(['promo_code' => 'ASSIGNED']);
        try {
            $this->service->bulkDelete($this->admin, $this->season->id, ['pass_request_ids' => [$free->id, $assigned->id]]);
            $this->fail('Assigned requests cannot be bulk deleted.');
        } catch (ValidationException) {
            $this->assertModelExists($free);
            $this->assertModelExists($assigned);
        }
    }

    public function test_admin_can_delete_and_bulk_delete_other_users_requests(): void
    {
        $single = $this->passRequest();
        $bulk = $this->passRequest();
        $this->service->delete($this->admin, $single->id);
        $this->assertModelMissing($single);
        $this->assertSame(1, $this->service->bulkDelete($this->admin, $this->season->id, ['pass_request_ids' => [$bulk->id]]));
        $this->assertModelMissing($bulk);
    }

    public function test_notifications_are_logged_and_require_force_to_be_sent_again(): void
    {
        $request = $this->passRequest(['promo_code' => 'EMAIL-CODE']);
        $data = ['pass_request_ids' => [$request->id]];

        $this->assertSame(1, $this->service->sendEmails($this->admin, $this->season->id, $data));
        $this->assertSame(0, $this->service->sendEmails($this->admin, $this->season->id, $data));
        $this->assertSame(1, $this->service->sendEmails($this->admin, $this->season->id, $data + ['force_send' => true]));
        Mail::assertSent(PassCodeNotification::class, 2);
        Mail::assertSent(PassCodeNotification::class, fn (PassCodeNotification $mail) => $mail->hasTo($request->passholder_email) && $mail->hasTo($this->admin->email));
        $this->assertNotNull($request->fresh()->email_notify_time);
        $this->assertDatabaseCount('email_logs', 2);
        $this->assertDatabaseHas('email_logs', ['event' => 'pass_code_notification', 'result' => 'sent']);
    }

    public function test_notification_selection_is_validated_before_sending_any_email(): void
    {
        $valid = $this->passRequest(['promo_code' => 'VALID']);
        $otherSeason = $this->passRequest(['season_id' => Season::factory()->create()->id, 'promo_code' => 'OTHER']);
        try {
            $this->service->sendEmails($this->admin, $this->season->id, ['pass_request_ids' => [$valid->id, $otherSeason->id]]);
            $this->fail('A cross-season selection must be rejected.');
        } catch (ValidationException) {
            Mail::assertNothingSent();
            $this->assertNull($valid->fresh()->email_notify_time);
        }
    }

    public function test_failed_notification_is_logged_without_marking_the_request_sent(): void
    {
        $request = $this->passRequest(['promo_code' => 'EMAIL-CODE']);
        Mail::shouldReceive('to')->once()->andThrow(new \RuntimeException('Mail unavailable'));

        $this->assertSame(0, $this->service->sendEmails($this->admin, $this->season->id, ['pass_request_ids' => [$request->id]]));
        $this->assertNull($request->fresh()->email_notify_time);
        $this->assertDatabaseHas('email_logs', ['result' => 'failed', 'error_message' => 'Mail unavailable']);
    }

    public function test_paginated_query_applies_country_and_status_filters_and_bounds_page_size(): void
    {
        $matching = $this->passRequest(['country' => null, 'promo_code' => 'USA']);
        $this->passRequest(['country' => 'Canada', 'promo_code' => 'CANADA']);
        $this->passRequest(['country' => 'USA']);

        $result = $this->service->query($this->admin, [
            'season_id' => $this->season->id, 'country' => 'USA', 'has_promo_code' => 'true', 'per_page' => 1,
        ]);
        $this->assertSame(1, $result->total());
        $this->assertSame($matching->id, $result->items()[0]->id);

        $this->expectException(ValidationException::class);
        $this->service->query($this->admin, ['per_page' => 101]);
    }

    private function passRequest(array $attributes = []): PassRequest
    {
        $seasonId = $attributes['season_id'] ?? $this->season->id;
        $type = SeasonPassType::factory()->create(['season_id' => $seasonId]);

        return PassRequest::create(array_merge([
            'user_id' => $this->owner->id,
            'season_id' => $seasonId,
            'season_pass_type_id' => $type->id,
            'pass_type' => $type->pass_type_name,
            'passholder_first_name' => 'Pass',
            'passholder_last_name' => 'Holder',
            'passholder_email' => 'holder@example.com',
            'passholder_birth_date' => '1990-01-01',
            'country' => 'USA',
        ], $attributes));
    }

    private function code(string $code, array $attributes = []): PromoCodeRepository
    {
        return PromoCodeRepository::create(array_merge([
            'promo_code' => $code,
            'season_id' => $this->season->id,
            'start_date' => now()->toDateString(),
            'expiration_date' => now()->addYear()->toDateString(),
            'country' => 'USA',
            'is_suspended' => false,
        ], $attributes));
    }
}
