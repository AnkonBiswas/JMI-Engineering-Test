<?php

namespace App\Repositories;

use App\Models\Anemometer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

/**
 * First concrete repository — encapsulates anemometer persistence so
 * controllers, jobs, and future services don't talk to Eloquent directly
 * for this domain. Establishes the pattern the AbstractRepository stub
 * has been waiting for.
 *
 * Specialised query methods (e.g. {@see paginateWithRecentAggregates})
 * live here rather than in a Service layer because they are pure
 * persistence concerns — SELECTs with subqueries, not orchestration.
 */
class AnemometerRepository extends AbstractRepository
{
    /**
     * @var class-string<Anemometer>
     */
    protected string $model = Anemometer::class;

    public function findOrFail(string $id): Anemometer
    {
        return Anemometer::query()->findOrFail($id);
    }

    public function findWithReadings(string $id): Anemometer
    {
        return Anemometer::query()->with('readings.tags')->findOrFail($id);
    }

    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return Anemometer::query()->paginate($perPage);
    }

    /**
     * Annotate each anemometer with its 24h and 7d average speeds, attach
     * its 5 most-recent readings, then paginate. Port of the Django
     * `@action` that backs GET /api/anemometers/recent-readings.
     */
    public function paginateWithRecentAggregates(int $perPage = 15): LengthAwarePaginator
    {
        $now = Carbon::now();
        $dayAgo = $now->copy()->subDay();
        $weekAgo = $now->copy()->subWeek();

        $anemometers = Anemometer::query()
            ->selectSub(
                fn ($q) => $q->from('readings')
                    ->selectRaw('AVG(speed)')
                    ->whereColumn('readings.anemometer_id', 'anemometers.id')
                    ->where('readings.recorded_at', '>=', $dayAgo),
                'average_daily_speed',
            )
            ->selectSub(
                fn ($q) => $q->from('readings')
                    ->selectRaw('AVG(speed)')
                    ->whereColumn('readings.anemometer_id', 'anemometers.id')
                    ->where('readings.recorded_at', '>=', $weekAgo),
                'average_weekly_speed',
            )
            ->addSelect('anemometers.*')
            ->paginate($perPage);

        $anemometers->getCollection()->transform(function (Anemometer $a): Anemometer {
            $recent = $a->readings()
                ->withoutGlobalScopes()
                ->with('tags')
                ->orderByDesc('recorded_at')
                ->limit(5)
                ->get();
            $a->setAttribute('recent_readings', $recent);

            return $a;
        });

        return $anemometers;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Anemometer
    {
        return Anemometer::create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Anemometer $anemometer, array $data): Anemometer
    {
        $anemometer->update($data);

        return $anemometer;
    }

    public function delete(Anemometer $anemometer): void
    {
        $anemometer->delete();
    }

    public function exists(string $id): bool
    {
        return Anemometer::query()->whereKey($id)->exists();
    }
}
