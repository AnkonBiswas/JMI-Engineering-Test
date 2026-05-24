<?php

/**
 * Feature tests for the w4l:import-readings artisan command.
 *
 * Covers the contract surface: happy path, historical recorded_at
 * preservation (the key reason the command exists rather than POSTs
 * to the API), bad-row skipping, missing file, and bad header.
 */

use App\Models\Anemometer;
use App\Models\Reading;
use Illuminate\Support\Facades\Artisan;

function writeTempCsv(string $content): string
{
    $path = tempnam(sys_get_temp_dir(), 'readings_').'.csv';
    file_put_contents($path, $content);

    return $path;
}

it('imports readings from a well-formed CSV', function (): void {
    $anemometer = Anemometer::factory()->create();
    $csv = "anemometer_id,speed,recorded_at,tags\n";
    $csv .= "{$anemometer->id},12.5,2025-01-15T10:00:00Z,gusty;stormy\n";
    $csv .= "{$anemometer->id},8.3,2025-01-15T11:00:00Z,calm\n";

    $path = writeTempCsv($csv);
    $exit = Artisan::call('w4l:import-readings', ['file' => $path]);
    unlink($path);

    expect($exit)->toBe(0);
    expect(Reading::count())->toBe(2);

    $slow = Reading::where('speed', 8.3)->first();
    expect($slow)->not->toBeNull();
    expect($slow->tags->pluck('name')->all())->toBe(['calm']);
});

it('preserves the recorded_at from the CSV (does not auto-stamp now)', function (): void {
    $anemometer = Anemometer::factory()->create();
    $csv = "anemometer_id,speed,recorded_at,tags\n";
    $csv .= "{$anemometer->id},5.0,2020-06-15T08:00:00Z,\n";

    $path = writeTempCsv($csv);
    Artisan::call('w4l:import-readings', ['file' => $path]);
    unlink($path);

    $reading = Reading::first();
    expect($reading)->not->toBeNull();
    expect($reading->recorded_at->year)->toBe(2020);
    expect($reading->recorded_at->month)->toBe(6);
    expect($reading->recorded_at->day)->toBe(15);
});

it('skips rows referencing an unknown anemometer', function (): void {
    $real = Anemometer::factory()->create();
    $fakeUuid = '00000000-0000-0000-0000-000000000000';

    $csv = "anemometer_id,speed,recorded_at,tags\n";
    $csv .= "{$real->id},10.0,2025-01-01T00:00:00Z,\n";
    $csv .= "{$fakeUuid},10.0,2025-01-01T00:00:00Z,\n";

    $path = writeTempCsv($csv);
    Artisan::call('w4l:import-readings', ['file' => $path]);
    unlink($path);

    expect(Reading::count())->toBe(1);
});

it('skips rows with non-numeric speed', function (): void {
    $anemometer = Anemometer::factory()->create();
    $csv = "anemometer_id,speed,recorded_at,tags\n";
    $csv .= "{$anemometer->id},not-a-number,2025-01-01T00:00:00Z,\n";

    $path = writeTempCsv($csv);
    Artisan::call('w4l:import-readings', ['file' => $path]);
    unlink($path);

    expect(Reading::count())->toBe(0);
});

it('returns failure when the file does not exist', function (): void {
    $exit = Artisan::call('w4l:import-readings', ['file' => '/no/such/file.csv']);

    expect($exit)->toBe(1);
});

it('returns failure when the header is wrong', function (): void {
    $path = writeTempCsv("wrong,header,here\n1,2,3");
    $exit = Artisan::call('w4l:import-readings', ['file' => $path]);
    unlink($path);

    expect($exit)->toBe(1);
});

it('tolerates a UTF-8 BOM at the start of the header', function (): void {
    $anemometer = Anemometer::factory()->create();
    $csv = "\xEF\xBB\xBFanemometer_id,speed,recorded_at,tags\n";
    $csv .= "{$anemometer->id},7.7,2025-03-01T00:00:00Z,\n";

    $path = writeTempCsv($csv);
    $exit = Artisan::call('w4l:import-readings', ['file' => $path]);
    unlink($path);

    expect($exit)->toBe(0);
    expect(Reading::count())->toBe(1);
});
