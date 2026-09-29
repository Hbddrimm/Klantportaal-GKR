<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

public function projects(): \Illuminate\Database\Eloquent\Relations\HasMany
{
    return $this->hasMany(Project::class);
}

public function comments(): \Illuminate\Database\Eloquent\Relations\HasMany
{
    return $this->hasMany(Comment::class);
}

/**
 * Controleer of de gebruiker een GKR Admin/Medewerker is.
 */
public function isAdmin(): bool
{
    return (bool) $this->is_admin; // Geeft true of false terug
}

/**
 * Outlook-kleurpreset van deze medewerker (ADR-011): zijn eigen keuze, of anders een automatische
 * kleur (zie `calendarColorAssignments()`).
 */
public function calendarColorPreset(): string
{
    if ($this->hasOwnCalendarColor()) {
        return $this->calendar_color;
    }

    return static::calendarColorAssignments()[$this->id] ?? config('calendar.default_palette')[0];
}

/**
 * Kleur per medewerker-id. Wie zelf een kleur koos, houdt die. De anderen krijgen op volgorde van
 * id een kleur uit het standaardpalet, waarbij kleuren die al door een collega gekozen zijn worden
 * overgeslagen. Zo hebben tot 8 medewerkers gegarandeerd elk een andere kleur (een verdeling op
 * `id % 8` gaf dezelfde kleur aan bijvoorbeeld id 3 en 11).
 *
 * Eén kleine query per aanroep; er zijn maar een handvol medewerkers.
 *
 * @return array<int, string>
 */
public static function calendarColorAssignments(): array
{
    $admins = static::query()->where('is_admin', true)->orderBy('id')->get(['id', 'calendar_color']);

    $chosen = $admins
        ->filter(fn (self $admin) => $admin->hasOwnCalendarColor())
        ->mapWithKeys(fn (self $admin) => [$admin->id => $admin->calendar_color]);

    $free = array_values(array_diff(config('calendar.default_palette'), $chosen->all()));
    if ($free === []) {
        $free = config('calendar.default_palette');
    }

    $assignments = $chosen->all();
    $rank = 0;

    foreach ($admins as $admin) {
        if (! isset($assignments[$admin->id])) {
            $assignments[$admin->id] = $free[$rank++ % count($free)];
        }
    }

    return $assignments;
}

private function hasOwnCalendarColor(): bool
{
    return $this->calendar_color !== null && isset(config('calendar.colors')[$this->calendar_color]);
}

public function calendarColorHex(): string
{
    return config('calendar.colors')[$this->calendarColorPreset()];
}

/**
 * "Mijn afspraken" (mine) of "Iedereen" (all) in het afsprakenoverzicht van een admin.
 */
public function prefersOwnAppointmentsOnly(): bool
{
    return $this->agenda_scope === 'mine';
}

}

