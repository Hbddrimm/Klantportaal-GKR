<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\BrandingResource;
use App\Models\Branding;
use Illuminate\Http\JsonResponse;

/**
 * White-label branding voor de installatie (FR-02 / NFR-03, ADR-010).
 *
 * Lezen is publiek: het inlogscherm van de app moet de huisstijl al tonen vóórdat er een
 * gebruiker bekend is. Schrijven kan alleen een admin (routes: `auth:sanctum` + `admin`,
 * plus BrandingPolicy). Welke branding je krijgt komt nooit uit de request: nu is er één
 * record; na #33 bepaalt de server de klant (sessie of request-host).
 */
class BrandingController extends Controller
{
    public function show(): JsonResponse
    {
        return $this->respond(Branding::current());
    }

    /**
     * Altijd 200: JsonResource zou 201 geven voor een net aangemaakt record, maar voor de
     * client is dit steeds "de huidige branding", geen nieuwe resource.
     */
    private function respond(Branding $branding): JsonResponse
    {
        return (new BrandingResource($branding))->response()->setStatusCode(200);
    }
}
