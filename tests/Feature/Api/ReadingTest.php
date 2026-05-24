<?php

/**
 * Port of the reading viewset tests from
 * wind_for_life/apps/anemometers/tests/test_anemometers.py.
 *
 * Divergences:
 *   - DRF validation errors are 400; Laravel FormRequest validation
 *     returns 422. Assertions updated accordingly.
 *   - Unauthenticated requests return 401 (Sanctum) rather than 403.
 */

use App\Models\Anemometer;
use App\Models\Reading;

it('returns paginated reading list', function (): void {
    actingAsUser();
    $number = 5;
    Reading::factory()->count($number)->create();

    $response = $this->getJson('/api/readings');

    $response->assertOk();
    expect(count($response->json('results')))->toBeGreaterThanOrEqual($number);
});

it('creates a reading with tags and persists it', function (): void {
    actingAsUser();
    $anemometer = Anemometer::factory()->create();

    $payload = [
        'speed' => 9.8,
        'recorded_at' => '2025-06-21T08:00:00Z',
        'anemometer' => $anemometer->id,
        'tags' => ['gusty', 'steady'],
    ];

    $response = $this->postJson('/api/readings', $payload);

    $response->assertCreated();
    expect(collect($response->json('tags'))->sort()->values()->all())
        ->toEqual(['gusty', 'steady']);
    expect(Reading::where('anemometer_id', $anemometer->id)->exists())->toBeTrue();
});

it('patches reading speed', function (): void {
    actingAsUser();
    $reading = Reading::factory()->create(['speed' => 5.0]);
    $newSpeed = 8.9;

    $response = $this->patchJson("/api/readings/{$reading->id}", ['speed' => $newSpeed]);

    $response->assertOk();
    expect((float) $response->json('speed'))->toBe($newSpeed);
});

it('rejects reading creation with missing fields', function (): void {
    actingAsUser();

    $response = $this->postJson('/api/readings', ['speed' => 10.5]);

    // Django/DRF: 400. Laravel FormRequest: 422.
    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['anemometer']);
});

it('rejects reading creation with invalid speed', function (): void {
    actingAsUser();
    $anemometer = Anemometer::factory()->create();

    $response = $this->postJson('/api/readings', [
        'speed' => 'invalid',
        'recorded_at' => '2025-06-21T10:00:00Z',
        'anemometer' => $anemometer->id,
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['speed']);
});

it('rejects unauthenticated reading creation', function (): void {
    // Django returns 403; Sanctum returns 401.
    $anemometer = Anemometer::factory()->create();

    $response = $this->postJson('/api/readings', [
        'speed' => 12.0,
        'recorded_at' => '2025-06-21T10:00:00Z',
        'anemometer' => $anemometer->id,
        'tags' => ['gusty'],
    ]);

    $response->assertStatus(401);
});

it('rejects reading creation for invalid anemometer uuid', function (): void {
    actingAsUser();

    $response = $this->postJson('/api/readings', [
        'speed' => 15.0,
        'recorded_at' => '2025-06-21T12:00:00Z',
        'anemometer' => 'invalid-uuid',
    ]);

    // Django/DRF: 400. Laravel FormRequest: 422.
    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['anemometer']);
});
