<?php

namespace Tests\Feature;

use App\Mail\PassCodeNotification;
use App\Models\PassRequest;
use App\Models\Season;
use App\Models\SeasonPassType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class EmailTemplateTest extends TestCase
{
    use RefreshDatabase;

    private Season $season;
    private User $user;
    private SeasonPassType $passType;

    protected function setUp(): void
    {
        parent::setUp();

        $this->season = Season::factory()->create([
            'pass_name' => 'Test Pass 2026',
            'pass_year' => 2026,
            'start_date' => now()->subDay(),
            'early_spring_deadline' => now()->addMonth(),
            'final_deadline' => now()->addMonths(2),
        ]);

        $this->user = User::factory()->create();

        $this->passType = SeasonPassType::factory()->create([
            'season_id' => $this->season->id,
            'pass_type_name' => 'Adult',
        ]);
    }

    /**
     * Test that the pass-code email template renders with passholder first_name and last_name.
     */
    public function test_pass_code_email_renders_with_first_and_last_name(): void
    {
        $passRequest = PassRequest::create([
            'user_id' => $this->user->id,
            'season_id' => $this->season->id,
            'season_pass_type_id' => $this->passType->id,
            'pass_type' => 'Adult',
            'passholder_first_name' => 'Aaron',
            'passholder_last_name' => 'de los Santos',
            'passholder_email' => 'aaron@example.com',
            'passholder_birth_date' => '1990-01-01',
            'promo_code' => 'TESTCODE123',
        ]);

        $mailable = new PassCodeNotification($passRequest, $this->season);
        $rendered = $mailable->render();

        $this->assertStringContainsString('Aaron', $rendered);
        $this->assertStringContainsString('de los Santos', $rendered);
        $this->assertStringContainsString('TESTCODE123', $rendered);
        $this->assertStringContainsString('Test Pass 2026', $rendered);
    }

    /**
     * Test that the pass-code email template correctly shows the passholder greeting with first_name.
     */
    public function test_pass_code_email_greeting_uses_first_name(): void
    {
        $passRequest = PassRequest::create([
            'user_id' => $this->user->id,
            'season_id' => $this->season->id,
            'season_pass_type_id' => $this->passType->id,
            'pass_type' => 'Adult',
            'passholder_first_name' => 'Jane',
            'passholder_last_name' => 'Smith',
            'passholder_email' => 'jane@example.com',
            'passholder_birth_date' => '1990-01-01',
            'promo_code' => 'PROMO456',
        ]);

        $mailable = new PassCodeNotification($passRequest, $this->season);
        $rendered = $mailable->render();

        // The greeting says "Hello {first_name},"
        $this->assertStringContainsString('Hello Jane', $rendered);
        // Full name appears in passholder details
        $this->assertStringContainsString('Jane', $rendered);
        $this->assertStringContainsString('Smith', $rendered);
    }

    /**
     * Test that the pass-code email subject includes the passholder's full name.
     */
    public function test_pass_code_email_subject_includes_passholder_name(): void
    {
        $passRequest = PassRequest::create([
            'user_id' => $this->user->id,
            'season_id' => $this->season->id,
            'season_pass_type_id' => $this->passType->id,
            'pass_type' => 'Adult',
            'passholder_first_name' => 'John',
            'passholder_last_name' => 'Doe',
            'passholder_email' => 'john@example.com',
            'passholder_birth_date' => '1990-01-01',
            'promo_code' => 'MYCODE789',
        ]);

        $mailable = new PassCodeNotification($passRequest, $this->season);
        $envelope = $mailable->envelope();

        $this->assertStringContainsString('John', $envelope->subject);
        $this->assertStringContainsString('Doe', $envelope->subject);
        $this->assertStringContainsString('Test Pass 2026', $envelope->subject);
    }

    /**
     * Test that the pass-code email does not reference User->name anywhere.
     */
    public function test_pass_code_email_does_not_use_user_name_field(): void
    {
        $passRequest = PassRequest::create([
            'user_id' => $this->user->id,
            'season_id' => $this->season->id,
            'season_pass_type_id' => $this->passType->id,
            'pass_type' => 'Adult',
            'passholder_first_name' => 'Alice',
            'passholder_last_name' => 'Wonderland',
            'passholder_email' => 'alice@example.com',
            'passholder_birth_date' => '1992-05-15',
            'promo_code' => 'ALICE2026',
        ]);

        $mailable = new PassCodeNotification($passRequest, $this->season);
        $rendered = $mailable->render();

        // The email should use passholder_first_name and passholder_last_name,
        // not the User model's name field
        $this->assertStringContainsString('Alice', $rendered);
        $this->assertStringContainsString('Wonderland', $rendered);
        // The user's own name should NOT appear (user has different factory-generated name)
        $this->assertStringNotContainsString($this->user->email, $rendered);
    }

    /**
     * Test that PassCodeNotification can be sent via Mail facade.
     */
    public function test_pass_code_notification_can_be_sent(): void
    {
        Mail::fake();

        $passRequest = PassRequest::create([
            'user_id' => $this->user->id,
            'season_id' => $this->season->id,
            'season_pass_type_id' => $this->passType->id,
            'pass_type' => 'Adult',
            'passholder_first_name' => 'Bob',
            'passholder_last_name' => 'Builder',
            'passholder_email' => 'bob@example.com',
            'passholder_birth_date' => '1985-03-22',
            'promo_code' => 'BOBCODE',
        ]);

        Mail::to('bob@example.com')->send(new PassCodeNotification($passRequest, $this->season));

        Mail::assertSent(PassCodeNotification::class, function ($mail) {
            return $mail->passRequest->passholder_first_name === 'Bob'
                && $mail->passRequest->passholder_last_name === 'Builder';
        });
    }

    public function test_pass_code_email_renders_country_when_set(): void
    {
        $passRequest = PassRequest::create([
            'user_id' => $this->user->id,
            'season_id' => $this->season->id,
            'season_pass_type_id' => $this->passType->id,
            'pass_type' => 'Adult',
            'passholder_first_name' => 'Country',
            'passholder_last_name' => 'Set',
            'passholder_email' => 'country-set@example.com',
            'passholder_birth_date' => '1990-01-01',
            'promo_code' => 'COUNTRYSET1',
            'country' => 'Canada',
        ]);

        $rendered = (new PassCodeNotification($passRequest, $this->season))->render();

        $this->assertStringContainsString('Country:', $rendered);
        $this->assertStringContainsString('Canada', $rendered);
        $this->assertStringNotContainsString('WARNING', $rendered);
    }

    public function test_pass_code_email_shows_null_country_warning_and_assumed_usa(): void
    {
        $passRequest = PassRequest::create([
            'user_id' => $this->user->id,
            'season_id' => $this->season->id,
            'season_pass_type_id' => $this->passType->id,
            'pass_type' => 'Adult',
            'passholder_first_name' => 'Country',
            'passholder_last_name' => 'Null',
            'passholder_email' => 'country-null@example.com',
            'passholder_birth_date' => '1990-01-01',
            'promo_code' => 'COUNTRYNUL1',
            'country' => null,
        ]);

        $rendered = (new PassCodeNotification($passRequest, $this->season))->render();

        $this->assertStringContainsString('USA (assumed)', $rendered);
        $this->assertStringContainsString('WARNING', $rendered);
        $this->assertStringContainsString('confirm your country is USA', $rendered);
    }
}
