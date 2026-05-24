<?php

namespace App\Console\Commands;

use App\Models\Anemometer;
use App\Models\Reading;
use App\Models\Tag;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Bulk-imports readings from a CSV file.
 *
 * Expected CSV format (header row required):
 *
 *   anemometer_id,speed,recorded_at,tags
 *   <uuid>,<float>,<ISO-8601 datetime>,<tag1;tag2;…>
 *
 * Notes:
 *  - Tags are semicolon-joined (matches the export format). Missing tags
 *    are auto-created by name, mirroring `ReadingController::syncTags`.
 *  - The model's `Reading::creating` hook stamps `recorded_at = now()`
 *    for `auto_now_add` parity with Django. For an import we explicitly
 *    *want* historical timestamps, so we save twice: first to get past
 *    the hook, then overwrite `recorded_at` with the CSV value. The
 *    two-write approach is deliberate — it makes the override visible
 *    instead of silently disabling all model events.
 *  - Rows with unknown anemometer IDs, invalid speeds, or unparseable
 *    timestamps are skipped and reported in the summary. No partial
 *    rollback — each row is its own transaction.
 *  - Not idempotent: re-importing the same file creates duplicate rows.
 *    Documented limitation; dedupe would require a uniqueness contract
 *    the schema doesn't currently express.
 */
class ImportReadingsCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'w4l:import-readings {file : Path to the CSV file}';

    /**
     * @var string
     */
    protected $description = 'Bulk-import readings from a CSV (anemometer_id,speed,recorded_at,tags).';

    public function handle(): int
    {
        $path = (string) $this->argument('file');

        if (! is_file($path)) {
            $this->error("File not found: {$path}");

            return self::FAILURE;
        }

        $handle = fopen($path, 'r');
        if ($handle === false) {
            $this->error("Could not open file: {$path}");

            return self::FAILURE;
        }

        $header = fgetcsv($handle);
        if ($header === false) {
            $this->error('File is empty.');
            fclose($handle);

            return self::FAILURE;
        }

        // Strip UTF-8 BOM from the first cell if present.
        $header[0] = ltrim((string) $header[0], "\xEF\xBB\xBF");

        $expected = ['anemometer_id', 'speed', 'recorded_at', 'tags'];
        $normalised = array_map('strtolower', array_map('trim', $header));
        if ($normalised !== $expected) {
            $this->error('Bad header. Expected: '.implode(',', $expected));
            $this->error('Got:               '.implode(',', $header));
            fclose($handle);

            return self::FAILURE;
        }

        $imported = 0;
        $skipped = 0;
        $rowNum = 1; // header was row 1

        while (($row = fgetcsv($handle)) !== false) {
            $rowNum++;

            if (count(array_filter($row, fn ($v) => $v !== '' && $v !== null)) === 0) {
                continue; // skip blank lines silently
            }

            [$anemometerId, $speedRaw, $recordedAtRaw, $tagsRaw] = array_pad($row, 4, '');

            if (! Anemometer::query()->whereKey($anemometerId)->exists()) {
                $this->warn("Row {$rowNum}: unknown anemometer_id '{$anemometerId}' — skipped");
                $skipped++;

                continue;
            }

            if (! is_numeric($speedRaw)) {
                $this->warn("Row {$rowNum}: invalid speed '{$speedRaw}' — skipped");
                $skipped++;

                continue;
            }

            try {
                $when = CarbonImmutable::parse((string) $recordedAtRaw);
            } catch (Throwable) {
                $this->warn("Row {$rowNum}: invalid recorded_at '{$recordedAtRaw}' — skipped");
                $skipped++;

                continue;
            }

            $tagNames = collect(explode(';', (string) $tagsRaw))
                ->map(fn ($t) => trim((string) $t))
                ->filter(fn ($t) => $t !== '')
                ->unique()
                ->values()
                ->all();

            DB::transaction(function () use ($anemometerId, $speedRaw, $when, $tagNames): void {
                // First write — hook stamps recorded_at = now().
                $reading = Reading::create([
                    'anemometer_id' => $anemometerId,
                    'speed' => (float) $speedRaw,
                ]);

                // Second write — overwrite with the historical timestamp.
                $reading->recorded_at = $when;
                $reading->save();

                if ($tagNames !== []) {
                    $tagIds = collect($tagNames)
                        ->map(fn (string $n) => Tag::firstOrCreate(['name' => $n])->id)
                        ->all();
                    $reading->tags()->sync($tagIds);
                }
            });

            $imported++;
        }

        fclose($handle);

        $this->info("Imported: {$imported}");
        if ($skipped > 0) {
            $this->warn("Skipped:  {$skipped}");
        }

        return self::SUCCESS;
    }
}
