<?php

namespace App\Http\Requests\Api;

use App\Models\Branding;
use App\Support\ContrastRatio;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Validatie bij opslaan, niet bij renderen (skill `white-label-branding`): een admin kan geen
 * onleesbaar palet of een naam met markup opslaan. De iOS-app toont dezelfde regels live;
 * deze hier zijn de autoritatieve (ADR-010).
 */
class UpdateBrandingRequest extends FormRequest
{
    private const HEX = '/^#[0-9A-F]{6}$/';

    public function authorize(): bool
    {
        return $this->user()?->can('update', Branding::current()) ?? false;
    }

    /**
     * Eén opslagvorm: naam zonder omringende spaties, kleuren in hoofdletters.
     */
    protected function prepareForValidation(): void
    {
        $normalized = [];

        if (is_string($this->input('organization_name'))) {
            $normalized['organization_name'] = trim($this->input('organization_name'));
        }

        foreach (['primary_color', 'accent_color'] as $field) {
            if (is_string($this->input($field))) {
                $normalized[$field] = strtoupper(trim($this->input($field)));
            }
        }

        $this->merge($normalized);
    }

    public function rules(): array
    {
        return [
            // Whitelist in plaats van blacklist: letters, cijfers en gangbare leestekens van
            // bedrijfsnamen. Daarmee kan de naam nooit markup of een CSS-declaratie worden.
            'organization_name' => ['required', 'string', 'min:2', 'max:40', "regex:/^[\\p{L}\\p{N} .,&'\\-]+$/u"],
            'primary_color' => ['required', 'string', 'regex:'.self::HEX],
            'accent_color' => ['required', 'string', 'regex:'.self::HEX],
        ];
    }

    /** Gewone taal, zonder verhoudingen of normcodes; dezelfde teksten als de iOS-app. */
    public const PRIMARY_TOO_LIGHT = 'Witte tekst is slecht leesbaar op deze kleur. Kies een donkerdere primaire kleur.';

    public const ACCENT_FADES_ON_WHITE = 'Het accent valt bijna weg op een witte achtergrond. Kies een donkerdere accentkleur.';

    public const ACCENT_NEEDS_LIGHTER = 'Het accent valt weg tegen de primaire kleur. Kies een lichtere accentkleur.';

    public const ACCENT_NEEDS_DARKER = 'Het accent valt weg tegen de primaire kleur. Kies een donkerdere accentkleur.';

    public const NO_ACCENT_POSSIBLE = 'Bij deze primaire kleur valt elk accent weg. Maak eerst de primaire kleur donkerder.';

    /**
     * Contrastregels (WCAG 2.2, ADR-010) over twee velden tegelijk. Afwijzen, niet stil
     * corrigeren: een automatisch aangepaste kleur is een huisstijl die niemand koos. De melding
     * zegt wat er misgaat en welke kant de beheerder op moet; de app biedt daarnaast een voorstel.
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return; // ongeldige hex: geen dubbele contrastfout
                }

                $primary = $this->input('primary_color');
                $accent = $this->input('accent_color');

                if (ContrastRatio::between($primary, '#FFFFFF') < config('branding.contrast.text')) {
                    $validator->errors()->add('primary_color', self::PRIMARY_TOO_LIGHT);
                }

                $accentMessage = self::accentMessage($primary, $accent);
                if ($accentMessage !== null) {
                    $validator->errors()->add('accent_color', $accentMessage);
                }
            },
        ];
    }

    /**
     * Welke kant het accent op moet. Het accent moet van wit én van de primaire kleur afsteken,
     * dus "donkerder" is niet altijd goed: naast donkerblauw moet een te donker accent juist
     * lichter. De grenzen volgen uit de contrastformule (L = relatieve luminantie):
     * verhouding = (L_licht + 0,05) / (L_donker + 0,05). Zelfde logica als
     * `BrandingValidation.accentMessage` in de iOS-app.
     */
    public static function accentMessage(string $primary, string $accent): ?string
    {
        $graphic = config('branding.contrast.graphic');
        $onWhite = ContrastRatio::between($accent, '#FFFFFF') >= $graphic;
        $onPrimary = ContrastRatio::between($accent, $primary) >= $graphic;

        if ($onWhite && $onPrimary) {
            return null;
        }

        $primaryLuminance = ContrastRatio::relativeLuminance($primary);
        $accentLuminance = ContrastRatio::relativeLuminance($accent);
        $lightestOnWhite = 1.05 / $graphic - 0.05;
        $darkerThanPrimary = ($primaryLuminance + 0.05) / $graphic - 0.05;
        $lighterThanPrimary = $graphic * ($primaryLuminance + 0.05) - 0.05;
        $canGoDarker = $darkerThanPrimary >= 0;
        $canGoLighter = $lighterThanPrimary <= $lightestOnWhite;

        if (! $canGoDarker && ! $canGoLighter) {
            return self::NO_ACCENT_POSSIBLE;
        }

        if (! $onWhite) {
            return self::ACCENT_FADES_ON_WHITE;
        }

        $goLighter = match (true) {
            $canGoLighter && ! $canGoDarker => true,
            $canGoDarker && ! $canGoLighter => false,
            default => $lighterThanPrimary - $accentLuminance <= $accentLuminance - $darkerThanPrimary,
        };

        return $goLighter ? self::ACCENT_NEEDS_LIGHTER : self::ACCENT_NEEDS_DARKER;
    }

    public function messages(): array
    {
        return [
            'organization_name.required' => 'Vul een organisatienaam in.',
            'organization_name.min' => 'De organisatienaam moet minimaal 2 tekens hebben.',
            'organization_name.max' => 'De organisatienaam mag maximaal 40 tekens hebben.',
            'organization_name.regex' => 'De organisatienaam mag alleen letters, cijfers, spaties en . , & \' - bevatten.',
            'primary_color.required' => 'Kies een primaire kleur.',
            'primary_color.regex' => 'Kies een geldige kleur, bijvoorbeeld #011936.',
            'accent_color.required' => 'Kies een accentkleur.',
            'accent_color.regex' => 'Kies een geldige kleur, bijvoorbeeld #059669.',
        ];
    }
}
