<?php

/**
 * Feature tests for GET /api/readings/export (Part-1 deliverable).
 *
 * Covers the contract surface: auth gating, default format, both formats'
 * content-type + filename + body shape, filter passthrough, validation,
 * and the empty-result-set edge case. Tag/anemometer filter *logic* is
 * unit-tested in ReadingFilterTest; here we only verify passthrough.
 */

use App\Models\Anemometer;
use App\Models\Reading;

it('rejects unauthenticated export requests', function (): void {
    $response = $this->getJson('/api/readings/export');

    $response->assertStatus(401);
});

it('defaults to csv when no format is given', function (): void {
    actingAsUser();
    Reading::factory()->count(3)->create();

    $response = $this->get('/api/readings/export');

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toStartWith('text/csv');
    expect($response->headers->get('Content-Disposition'))->toContain('attachment');
    expect($response->headers->get('Content-Disposition'))->toContain('.csv');
});

it('exports csv with a header row and one line per reading', function (): void {
    actingAsUser();
    Reading::factory()->count(3)->create();

    $response = $this->get('/api/readings/export?format=csv');

    $response->assertOk();
    $body = $response->streamedContent();

    // Strip the UTF-8 BOM before splitting.
    $body = ltrim($body, "\xEF\xBB\xBF");
    $lines = array_values(array_filter(explode("\n", trim($body))));

    expect($lines[0])->toBe('id,speed,recorded_at,tags');
    expect($lines)->toHaveCount(4); // header + 3 data rows
});

it('exports json as a flat array of ReadingResource-shaped objects', function (): void {
    actingAsUser();
    Reading::factory()->count(3)->create();

    $response = $this->get('/api/readings/export?format=json');

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toStartWith('application/json');

    $decoded = json_decode($response->streamedContent(), true);

    expect($decoded)->toBeArray();
    expect($decoded)->toHaveCount(3);
    expect($decoded[0])->toHaveKeys(['id', 'speed', 'recorded_at', 'tags']);
});

it('honors the anemometer filter when exporting', function (): void {
    actingAsUser();
    $target = Anemometer::factory()->create();
    $other = Anemometer::factory()->create();
    Reading::factory()->count(2)->for($target)->create();
    Reading::factory()->count(3)->for($other)->create();

    $response = $this->get("/api/readings/export?format=json&anemometer={$target->id}");

    $response->assertOk();
    $decoded = json_decode($response->streamedContent(), true);
    expect($decoded)->toHaveCount(2);
});

it('returns 422 on an unsupported format', function (): void {
    actingAsUser();

    $response = $this->getJson('/api/readings/export?format=xml');

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['format']);
});

it('returns an empty body when no readings match', function (): void {
    actingAsUser();

    $response = $this->get('/api/readings/export?format=json');

    $response->assertOk();
    expect(trim($response->streamedContent()))->toBe('[]');
});
