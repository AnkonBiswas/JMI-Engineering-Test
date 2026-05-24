<?php

/**
 * Port of wind_for_life/apps/anemometers/tests/test_anemometers.py
 * (anemometer viewset section).
 *
 * Divergences from the Django original:
 *   - DRF returns 403 for unauthenticated requests. Laravel Sanctum
 *     returns 401 by default via AuthenticationException — we assert
 *     that instead. This is an intentional framework-level difference.
 */

use App\Models\Anemometer;
use App\Models\Reading;

it('returns anemometer detail with id', function (): void {
    actingAsUser();
    $anemometer = Anemometer::factory()->withReadings(5)->create();

    $response = $this->getJson("/api/anemometers/{$anemometer->id}");

    $response->assertOk();
    expect($response->json('id'))->toBe($anemometer->id);
});

it('returns paginated recent readings with aggregate fields', function (): void {
    actingAsUser();
    $numAnemometers = 5;
    Anemometer::factory()->count($numAnemometers)->create();

    $response = $this->getJson('/api/anemometers/recent-readings');

    $response->assertOk();
    expect($response->json('results'))->toHaveCount($numAnemometers);
    $first = $response->json('results.0');
    expect($first)->toHaveKeys([
        'recent_readings',
        'average_daily_speed',
        'average_weekly_speed',
    ]);
});

it('returns 404 when anemometer does not exist', function (): void {
    actingAsUser();

    $response = $this->getJson('/api/anemometers/'.uuidInvalid());

    $response->assertNotFound();
});

it('rejects unauthenticated anemometer list access', function (): void {
    // NOTE: Django/DRF returns 403 here. Laravel's Sanctum "auth:sanctum"
    // middleware throws AuthenticationException, which the exception
    // handler renders as 401 for JSON requests. This is the documented
    // framework-level difference.
    $response = $this->getJson('/api/anemometers');

    $response->assertStatus(401);
});

it('patches anemometer name', function (): void {
    actingAsUser();
    $anemometer = Anemometer::factory()->create(['name' => 'Old Name']);

    $response = $this->patchJson(
        "/api/anemometers/{$anemometer->id}",
        ['name' => 'Updated Name'],
    );

    $response->assertOk();
    expect($response->json('name'))->toBe('Updated Name');
});
