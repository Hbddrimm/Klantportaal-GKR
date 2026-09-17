<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DocumentController;
use Illuminate\Support\Facades\Route;

/**
 * Nieuwe API-laag voor de mobiele app (ADR-002/ADR-003). Bestond niet in de AS-IS-codebase
 * (requirements.md §2.2) — dit is de walking-skeleton-scaffolding uit het plan
 * "Infrastructuur, testen en API-laag vóór de iOS-basis".
 *
 * Foutresponsconventie (skill `api-design`): bewust geen eigen exception-handler bovenop
 * Laravel's standaardgedrag voor JSON-requests — dat geeft al consistent `{"message": "..."}`
 * (plus `{"errors": {...}}` bij 422-validatiefouten) voor elke fout op deze routes. Expliciet
 * vastgelegd zodat dit een bewuste keuze is, geen toevallige afwezigheid van een conventie.
 */
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/documenten', [DocumentController::class, 'index']);
});
