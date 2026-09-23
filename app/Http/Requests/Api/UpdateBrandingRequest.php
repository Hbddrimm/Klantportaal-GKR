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

    /**
     * Contrastregels (WCAG 2.2) over twee velden tegelijk. Afwijzen, niet stil corrigeren:
     * een automatisch aangepaste kleur is een huisstijl die niemand koos.
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
                $text = config('branding.contrast.text');
                $graphic = config('branding.contrast.graphic');

                $ratio = ContrastRatio::between($primary, '#FFFFFF');
                if ($ratio < $text) {
                    $validator->errors()->add('primary_color', sprintf(
                        'De primaire kleur heeft te weinig contrast met wit (%s:1). Witte tekst op deze kleur moet minimaal %s:1 halen (WCAG AA).',
                        $this->formatRatio($ratio), $this->formatRatio($text),
                    ));
                }

                $ratio = ContrastRatio::between($accent, '#FFFFFF');
                if ($ratio < $graphic) {
                    $validator->errors()->add('accent_color', sprintf(
                        'De accentkleur heeft te weinig contrast met wit (%s:1). Minimaal %s:1 is nodig om accenten op een lichte achtergrond te zien (WCAG 1.4.11).',
                        $this->formatRatio($ratio), $this->formatRatio($graphic),
                    ));
                }

                $ratio = ContrastRatio::between($accent, $primary);
                if ($ratio < $graphic) {
                    $validator->errors()->add('accent_color', sprintf(
                        'De accentkleur heeft te weinig contrast met de primaire kleur (%s:1). Minimaal %s:1 is nodig (WCAG 1.4.11).',
                        $this->formatRatio($ratio), $this->formatRatio($graphic),
                    ));
                }
            },
        ];
    }

    public function messages(): array
    {
        return [
            'organization_name.required' => 'Vul een organisatienaam in.',
            'organization_name.min' => 'De organisatienaam moet minimaal 2 tekens hebben.',
            'organization_name.max' => 'De organisatienaam mag maximaal 40 tekens hebben.',
            'organization_name.regex' => 'De organisatienaam mag alleen letters, cijfers, spaties en . , & \' - bevatten.',
            'primary_color.required' => 'Kies een primaire kleur.',
            'primary_color.regex' => 'Gebruik een hexkleur in het formaat #RRGGBB.',
            'accent_color.required' => 'Kies een accentkleur.',
            'accent_color.regex' => 'Gebruik een hexkleur in het formaat #RRGGBB.',
        ];
    }

    /**
     * Afkappen (niet afronden) op twee decimalen, zodat 4,496 niet als "4,50" wordt getoond
     * terwijl het de 4,5-drempel níet haalt. Nederlandse decimale komma.
     */
    private function formatRatio(float $ratio): string
    {
        return number_format(floor($ratio * 100) / 100, 2, ',', '');
    }
}
