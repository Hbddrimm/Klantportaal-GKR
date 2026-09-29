<?php

/**
 * Outlook-agendakoppeling via Microsoft Graph (FR-08, ADR-011).
 *
 * Eén koppeling voor heel GKR (applicatierechten, client credentials). Geheimen staan alleen
 * in `.env` / Railway-variabelen. Standaard staat de driver op `fake`, zodat lokaal en in CI
 * nooit een echte agenda wordt aangeroepen.
 */
return [

    // `graph` = echte Outlook-agenda's, `fake` = in-memory (lokaal en tests).
    'driver' => env('CALENDAR_DRIVER', 'fake'),

    // Gedeelde overzichtsagenda (info@gkr.nl): staat als optionele deelnemer op elke afspraak
    // en krijgt per medewerker een kleur. Leeg = geen overzichtsagenda.
    'overview_mailbox' => env('CALENDAR_OVERVIEW_MAILBOX'),

    'timezone' => 'Europe/Amsterdam',

    'graph' => [
        'tenant_id' => env('MICROSOFT_TENANT_ID'),
        'client_id' => env('MICROSOFT_CLIENT_ID'),
        'client_secret' => env('MICROSOFT_CLIENT_SECRET'),
        'base_url' => 'https://graph.microsoft.com/v1.0',
        'timeout_seconds' => 10,
    ],

    /*
    | Kleuren per medewerker. Outlook kent alleen vaste kleur-presets voor categorieën; de app
    | krijgt de bijbehorende hexwaarde. Een medewerker zonder eigen keuze krijgt automatisch een
    | kleur uit `default_palette` (op volgorde van id), zodat collega's elkaar niet overlappen.
    */
    'colors' => [
        'preset0' => '#E74856',  // rood
        'preset1' => '#FF8C00',  // oranje
        'preset3' => '#FFAB45',  // geel
        'preset4' => '#00B294',  // groen
        'preset5' => '#00B7C3',  // blauwgroen
        'preset7' => '#0078D4',  // blauw
        'preset8' => '#8764B8',  // paars
        'preset9' => '#C30052',  // cranberry
        'preset10' => '#5D6F7F', // staalblauw
        'preset12' => '#69797E', // grijs
    ],

    'default_palette' => ['preset7', 'preset4', 'preset1', 'preset8', 'preset0', 'preset5', 'preset9', 'preset3'],
];
