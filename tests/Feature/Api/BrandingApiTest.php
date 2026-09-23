<?php

namespace Tests\Feature\Api;

use App\Models\Branding;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
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

    // --- Wijzigen (admin) --------------------------------------------------------------------

    private const VALID = [
        'organization_name' => 'Acme Bouw',
        'primary_color' => '#431407',
        'accent_color' => '#EA580C',
    ];

    public function test_admin_can_update_branding_and_it_takes_effect_without_deploy(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->putJson('/api/branding', self::VALID)
            ->assertOk()
            ->assertJson(self::VALID);

        // Een verse, publieke read ziet de wijziging direct.
        $this->getJson('/api/branding')->assertJson(self::VALID);
        $this->assertSame(1, Branding::count());
    }

    public function test_second_update_changes_the_same_record(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->putJson('/api/branding', self::VALID)->assertOk();
        $this->putJson('/api/branding', [...self::VALID, 'organization_name' => 'Acme Wonen'])->assertOk();

        $this->assertSame(1, Branding::count());
        $this->assertSame('Acme Wonen', Branding::current()->organization_name);
    }

    public function test_colours_are_normalized_to_uppercase_and_name_is_trimmed(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->putJson('/api/branding', [
            'organization_name' => '  Acme Bouw  ',
            'primary_color' => '#431407',
            'accent_color' => '#ea580c',
        ])->assertOk()->assertJson(['organization_name' => 'Acme Bouw', 'accent_color' => '#EA580C']);
    }

    public function test_deliberately_bad_palette_is_rejected_and_nothing_is_saved(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->putJson('/api/branding', [
            'organization_name' => 'Acme Bouw',
            'primary_color' => '#FFFFFF',
            'accent_color' => '#FFFF00',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['primary_color', 'accent_color']);

        $this->assertSame(0, Branding::count());
    }

    public function test_contrast_error_names_the_measured_ratio(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        // #767676 op wit = 4,54:1 (haalt het); #777777 = 4,47:1 (net niet).
        $response = $this->putJson('/api/branding', [...self::VALID, 'primary_color' => '#777777'])
            ->assertUnprocessable();

        $this->assertStringContainsString('(4,47:1)', $response->json('errors.primary_color.0'));

        $this->putJson('/api/branding', [...self::VALID, 'primary_color' => '#767676', 'accent_color' => '#059669'])
            ->assertJsonMissingValidationErrors(['primary_color']);
    }

    public function test_accent_must_contrast_with_primary(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        // #EA580C haalt 3:1 tegen wit, maar niet tegen een middenbruine primary.
        $this->putJson('/api/branding', [...self::VALID, 'primary_color' => '#7C2D12'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['accent_color'])
            ->assertJsonMissingValidationErrors(['primary_color']);
    }

    /**
     * @return array<string, array{0: string, 1: mixed}>
     */
    public static function invalidInput(): array
    {
        return [
            'naam met markup' => ['organization_name', '<script>alert(1)</script>'],
            'naam met css' => ['organization_name', 'Acme; color: red'],
            'naam te lang' => ['organization_name', str_repeat('a', 41)],
            'naam te kort' => ['organization_name', 'A'],
            'naam leeg' => ['organization_name', '   '],
            'drie-cijferige hex' => ['primary_color', '#abc'],
            'rgb-notatie' => ['primary_color', 'rgb(1,2,3)'],
            'kleurnaam' => ['accent_color', 'red'],
            'css-injectie' => ['primary_color', '#011936; background:url(x)'],
            'geen string' => ['accent_color', ['#059669']],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('invalidInput')]
    public function test_invalid_input_is_rejected(string $field, mixed $value): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->putJson('/api/branding', [...self::VALID, $field => $value])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([$field]);

        $this->assertSame(0, Branding::count());
    }

    public function test_missing_field_is_rejected(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->putJson('/api/branding', ['organization_name' => 'Acme Bouw'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['primary_color', 'accent_color']);
    }

    public function test_klant_id_in_the_body_is_ignored(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->putJson('/api/branding', [...self::VALID, 'klant_id' => 99, 'logo_path' => '../.env'])
            ->assertOk();

        $this->assertNull(Branding::current()->klant_id);
        $this->assertNull(Branding::current()->logo_path);
    }

    // --- Autorisatie ---------------------------------------------------------------------------

    public function test_update_without_token_is_unauthorized(): void
    {
        $this->putJson('/api/branding', self::VALID)->assertUnauthorized();

        $this->assertSame(0, Branding::count());
    }

    public function test_non_admin_cannot_update_branding(): void
    {
        $existing = Branding::factory()->create(['organization_name' => 'Origineel']);
        Sanctum::actingAs(User::factory()->create());

        $this->putJson('/api/branding', self::VALID)
            ->assertStatus(403); // expliciet 403, geen 302-redirect naar HTML

        $this->assertSame('Origineel', $existing->fresh()->organization_name);
    }
}
