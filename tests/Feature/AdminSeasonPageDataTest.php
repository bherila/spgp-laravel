<?php

namespace Tests\Feature;

use App\Models\PassRequest;
use App\Models\PromoCodeRepository;
use App\Models\Season;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AdminSeasonPageDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_promo_code_page_embeds_the_same_season_scoped_data_as_refresh(): void
    {
        $admin = User::factory()->admin()->create();
        $season = Season::factory()->create();
        $this->code($season, 'AVAILABLE');
        $otherSeason = Season::factory()->create();
        $this->code($otherSeason, 'OTHER-SEASON');

        $this->actingAs($admin);
        $pageData = $this->pageData('/admin/seasons/'.$season->id.'/promo-codes');
        $apiData = $this->getJson('/api/admin/seasons/'.$season->id.'/promo-codes/list')->assertOk()->json();

        $this->assertSame($apiData, $pageData);
        $this->assertSame(['AVAILABLE'], array_column($pageData['promo_codes'], 'promo_code'));
    }

    public function test_pass_request_page_embeds_requests_and_only_available_code_count(): void
    {
        $admin = User::factory()->admin()->create();
        $season = Season::factory()->create();
        $otherSeason = Season::factory()->create();
        $this->code($season, 'AVAILABLE');
        $this->code($season, 'SUSPENDED', ['is_suspended' => true]);
        $this->code($season, 'ASSIGNED');
        $this->code($otherSeason, 'OTHER-SEASON');
        $record = PassRequest::create([
            'user_id' => $admin->id,
            'season_id' => $season->id,
            'passholder_email' => 'passholder@example.test',
            'passholder_first_name' => 'Alex',
            'passholder_last_name' => 'Example',
            'passholder_birth_date' => '1990-01-01',
            'pass_type' => 'Adult',
            'is_renewal' => false,
            'promo_code' => 'ASSIGNED',
        ]);

        $this->actingAs($admin);
        $pageData = $this->pageData('/admin/seasons/'.$season->id.'/pass-requests');
        $apiData = $this->getJson('/api/admin/seasons/'.$season->id.'/pass-requests/list')->assertOk()->json();

        $this->assertSame($apiData, $pageData);
        $this->assertSame(1, $pageData['repo_count']);
        $this->assertSame([$record->id], array_column($pageData['pass_requests'], 'id'));
    }

    #[DataProvider('adminPages')]
    public function test_non_admin_cannot_receive_embedded_season_data(string $path): void
    {
        $season = Season::factory()->create();
        $this->actingAs(User::factory()->create())
            ->get('/admin/seasons/'.$season->id.'/'.$path)
            ->assertForbidden();
    }

    public static function adminPages(): array
    {
        return [['pass-requests'], ['promo-codes']];
    }

    private function pageData(string $url): array
    {
        $response = $this->get($url)->assertOk();
        $document = new \DOMDocument;
        @$document->loadHTML($response->getContent());
        $script = $document->getElementById('page-data');
        $this->assertNotNull($script);
        $this->assertSame('application/json', $script->getAttribute('type'));

        return json_decode($script->textContent, true, 512, JSON_THROW_ON_ERROR);
    }

    private function code(Season $season, string $code, array $attributes = []): void
    {
        PromoCodeRepository::create([
            'promo_code' => $code,
            'season_id' => $season->id,
            'start_date' => '2026-01-01',
            'expiration_date' => '2027-09-01',
            'country' => null,
            'is_suspended' => false,
            ...$attributes,
        ]);
    }
}
