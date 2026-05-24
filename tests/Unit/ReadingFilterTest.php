<?php

/**
 * Direct unit tests for the ReadingFilter class — mirrors the
 * filterset-level tests in test_anemometers.py::test_filter_tags_any,
 * test_filter_tags_exact_single_tag, and test_filter_tags_exact_multiple_tags.
 *
 * The filter is a static helper at App\Http\Filters\ReadingFilter that
 * applies tag-based constraints to a Reading query builder.
 */

use App\Http\Filters\ReadingFilter;
use App\Models\Anemometer;
use App\Models\Reading;
use App\Models\Tag;

beforeEach(function (): void {
    $anemometer = Anemometer::factory()->create();

    $resolve = fn (array $names) => collect($names)
        ->map(fn (string $n) => Tag::firstOrCreate(['name' => $n]));

    $this->readings = [
        'gusty' => Reading::factory()->for($anemometer)->hasAttached($resolve(['gusty']))->create(),
        'gusty_drafty' => Reading::factory()->for($anemometer)->hasAttached($resolve(['gusty', 'drafty']))->create(),
        'drafty_stormy' => Reading::factory()->for($anemometer)->hasAttached($resolve(['drafty', 'stormy']))->create(),
        'calm' => Reading::factory()->for($anemometer)->hasAttached($resolve(['calm']))->create(),
    ];
});

function applyReadingFilter(array $filters): \Illuminate\Support\Collection
{
    return ReadingFilter::apply(Reading::query(), $filters)->pluck('id');
}

it('filter_tags_any returns readings with any of the requested tags', function (): void {
    $ids = applyReadingFilter(['tags_any' => 'gusty,drafty']);

    expect($ids)->toContain($this->readings['gusty']->id);
    expect($ids)->toContain($this->readings['gusty_drafty']->id);
    expect($ids)->toContain($this->readings['drafty_stormy']->id);
    expect($ids)->not->toContain($this->readings['calm']->id);
});

it('filter_tags_exact single tag returns only exactly-that-tag readings', function (): void {
    $ids = applyReadingFilter(['tags_exact' => 'gusty']);

    expect($ids)->toContain($this->readings['gusty']->id);
    expect($ids)->not->toContain($this->readings['gusty_drafty']->id);
    expect($ids)->not->toContain($this->readings['drafty_stormy']->id);
    expect($ids)->not->toContain($this->readings['calm']->id);
});

it('filter_tags_exact multiple tags returns only the exact set match', function (): void {
    $ids = applyReadingFilter(['tags_exact' => 'gusty,drafty']);

    expect($ids)->toContain($this->readings['gusty_drafty']->id);
    expect($ids)->not->toContain($this->readings['gusty']->id);
    expect($ids)->not->toContain($this->readings['drafty_stormy']->id);
    expect($ids)->not->toContain($this->readings['calm']->id);
});
