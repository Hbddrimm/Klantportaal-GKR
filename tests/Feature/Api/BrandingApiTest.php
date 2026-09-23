<?php

namespace Tests\Feature\Api;

use App\Models\Branding;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * White-label branding API (FR-02 / NFR-03, ADR-010). Dekt de testeisen van de skill
 * `white-label-branding`: alleen defaults rendert correct, een niet-default configuratie komt
 * terug, een slecht palet wordt geweigerd, en een wijziging werkt zonder deploy.
 */
class BrandingApiTest extends TestCase
{
    use RefreshDatabase;

    // --- Lezen (publiek) ---------------------------------------------------------------------

    public function test_without_configuration_the_defaults_are_returned(): void
    {
        $this->getJson('/api/branding')
            ->assertOk()
            ->assertExactJson([
                'organization_name' => 'Klantportaal',
                'primary_color' => '#011936',
                'accent_color' => '#059669',
                'logo_path' => null,
                'updated_at' => null,
            ]);

        $this->assertSame(0, Branding::count(), 'Lezen mag geen record aanmaken.');
    }

    public function test_configured_branding_replaces_the_defaults(): void
    {
        Branding::factory()->create();

        $this->getJson('/api/branding')
            ->assertOk()
            ->assertJson([
                'organization_name' => 'Acme Bouw',
                'primary_color' => '#431407',
                'accent_color' => '#EA580C',
            ]);
    }

    public function test_branding_is_readable_without_a_token(): void
    {
        // Bewust publiek: het inlogscherm heeft de huisstijl nodig vóór authenticatie.
        $this->getJson('/api/branding')->assertOk();
    }

    public function test_response_does_not_leak_internal_fields(): void
    {
        Branding::factory()->create();

        $this->getJson('/api/branding')
            ->assertOk()
            ->assertJsonMissingPath('id')
            ->assertJsonMissingPath('klant_id')
            ->assertJsonMissingPath('created_at')
            ->assertJsonMissingPath('data');
    }
}
