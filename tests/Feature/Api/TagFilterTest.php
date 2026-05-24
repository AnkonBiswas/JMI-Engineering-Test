<?php

/**
 * Port of the tags_any / tags_exact filter tests.
 *
 * The Django originals exercise the FilterSet class directly. Here we
 * drive the actual API endpoint — same semantics, but through the HTTP
 * boundary so the query-string parsing in the Laravel filter layer is
 * exercised too. A pure-unit counterpart lives at
 * tests/Unit/ReadingFilterTest.php.
 */

use App\Models\Anemometer;
use App\Models\Reading;

beforeEach(function (): void {
    actingAsUser();
    $anemometer = Anemometer::factory()->create();

    $this->readings = [
        'gusty' => Reading::factory()->for($anemometer)->hasAttached(
            tagsForNames(['gusty'])
        )->create(),
        'gusty_drafty' => Reading::factory()->for($anemometer)->hasAttached(
            tagsForNames(['gusty', 'drafty'])
        )->create(),
        'drafty_stormy' => Reading::factory()->for($anemometer)->hasAttached(
            tagsForNames(['drafty', 'stormy'])
        )->create(),
        'calm' => Reading::factory()->for($anemometer)->hasAttached(
            tagsForNames(['calm'])
        )->create(),
    ];
});

it('tags_any includes readings sharing any requested tag', function (): void {
    $response = $this->getJson('/api/readings?tags_any=gusty,drafty');
    $response->assertOk();

    $ids = collect($response->json('results'))->pluck('id')->all();

    expect($ids)->toContain($this->readings['gusty']->id);
    expect($ids)->toContain($this->readings['gusty_drafty']->id);
    expect($ids)->toContain($this->readings['drafty_stormy']->id);
    expect($ids)->not->toContain($this->readings['calm']->id);
});

it('tags_exact with a single tag returns only exact matches', function (): void {
    $response = $this->getJson('/api/readings?tags_exact=gusty');
    $response->assertOk();

    $ids = collect($response->json('results'))->pluck('id')->all();

    expect($ids)->toContain($this->readings['gusty']->id);
    expect($ids)->not->toContain($this->readings['gusty_drafty']->id);
    expect($ids)->not->toContain($this->readings['drafty_stormy']->id);
    expect($ids)->not->toContain($this->readings['calm']->id);
});

it('tags_exact with multiple tags returns only the exact set match', function (): void {
    $response = $this->getJson('/api/readings?tags_exact=gusty,drafty');
    $response->assertOk();

    $ids = collect($response->json('results'))->pluck('id')->all();

    expect($ids)->toContain($this->readings['gusty_drafty']->id);
    expect($ids)->not->toContain($this->readings['gusty']->id);
    expect($ids)->not->toContain($this->readings['drafty_stormy']->id);
    expect($ids)->not->toContain($this->readings['calm']->id);
});

/**
 * Local helper — resolves (or creates) Tag rows for the given names and
 * returns them as a collection ready to be passed to hasAttached().
 */
function tagsForNames(array $names): \Illuminate\Support\Collection
{
    return collect($names)->map(fn (string $name) => \App\Models\Tag::firstOrCreate(['name' => $name]));
}
