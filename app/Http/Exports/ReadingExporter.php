<?php

namespace App\Http\Exports;

use App\Http\Resources\ReadingResource;
use App\Models\Reading;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams readings to CSV or JSON for the GET /api/readings/export endpoint.
 *
 * Single class, two methods — no interface, no factory, no registry. If a
 * third format ever shows up, extract an interface at that point.
 *
 * Memory is bounded via Eloquent's `lazy(1000)` so the readings table can
 * grow unbounded without changing the export's footprint. Tags are eager-
 * loaded per chunk so we don't N+1 across thousands of rows.
 */
class ReadingExporter
{
    /**
     * @param  Builder<Reading>  $query
     */
    public function streamCsv(Builder $query): StreamedResponse
    {
        $filename = 'readings-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($query): void {
            $handle = fopen('php://output', 'w');

            // UTF-8 BOM so Excel opens the file without mangling tag names.
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, ['id', 'speed', 'recorded_at', 'tags']);

            $query->with('tags')->lazy(1000)->each(function (Reading $reading) use ($handle): void {
                fputcsv($handle, [
                    $reading->id,
                    $reading->speed,
                    optional($reading->recorded_at)->toIso8601String(),
                    $reading->tags->pluck('name')->implode(';'),
                ]);
            });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Streams a JSON array of reading objects matching the ReadingResource
     * shape used by GET /api/readings. Deliberately NOT wrapped in the
     * DRF pagination envelope — an export is an atomic complete dataset.
     *
     * @param  Builder<Reading>  $query
     */
    public function streamJson(Builder $query): StreamedResponse
    {
        $filename = 'readings-'.now()->format('Ymd-His').'.json';

        return response()->streamDownload(function () use ($query): void {
            echo '[';
            $first = true;
            $query->with('tags')->lazy(1000)->each(function (Reading $reading) use (&$first): void {
                if (! $first) {
                    echo ',';
                }
                echo json_encode((new ReadingResource($reading))->resolve());
                $first = false;
            });
            echo ']';
        }, $filename, [
            'Content-Type' => 'application/json',
        ]);
    }
}
