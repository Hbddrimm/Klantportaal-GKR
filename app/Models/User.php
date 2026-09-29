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
 * Outlook-kleurpreset van deze medewerker (ADR-011). Zonder eigen keuze een vaste kleur uit het
 * standaardpalet op basis van het id, zodat collega's automatisch verschillende kleuren krijgen.
 */
public function calendarColorPreset(): string
{
    $colors = config('calendar.colors');

    if ($this->calendar_color && isset($colors[$this->calendar_color])) {
        return $this->calendar_color;
    }

    $palette = config('calendar.default_palette');

    return $palette[($this->id ?? 0) % count($palette)];
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

