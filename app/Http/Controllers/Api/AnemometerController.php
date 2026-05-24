<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\StoreAnemometerRequest;
use App\Http\Requests\UpdateAnemometerRequest;
use App\Http\Resources\AnemometerDetailResource;
use App\Http\Resources\AnemometerResource;
use App\Http\Resources\RecentReadingsAnemometerResource;
use App\Http\Responses\DrfPagination;
use App\Models\Anemometer;
use App\Repositories\AnemometerRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

/**
 * Port of wind_for_life/apps/anemometers/api/views.py::AnemometerViewSet.
 *
 * Persistence is delegated to {@see AnemometerRepository} — the first
 * concrete repository in the codebase. Controllers stay thin: parse HTTP,
 * call the repository, shape the response.
 */
class AnemometerController extends Controller
{
    public function __construct(private readonly AnemometerRepository $anemometers) {}

    /**
     * GET /api/anemometers — paginated list.
     *
     * @return array<string, mixed>
     */
    public function index(Request $request): array
    {
        return DrfPagination::shape(
            $this->anemometers->paginate(),
            fn (Anemometer $a) => (new AnemometerResource($a))->resolve(),
        );
    }

    /**
     * GET /api/anemometers/{id} — detail with eager-loaded readings.
     */
    public function show(string $id): AnemometerDetailResource
    {
        return new AnemometerDetailResource(
            $this->anemometers->findWithReadings($id),
        );
    }

    /**
     * POST /api/anemometers — create.
     */
    public function store(StoreAnemometerRequest $request): JsonResponse
    {
        $anemometer = $this->anemometers->create($request->validated());

        return (new AnemometerResource($anemometer))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * PUT/PATCH /api/anemometers/{id} — update.
     */
    public function update(UpdateAnemometerRequest $request, string $id): AnemometerResource
    {
        $anemometer = $this->anemometers->findOrFail($id);
        $this->anemometers->update($anemometer, $request->validated());

        return new AnemometerResource($anemometer);
    }

    /**
     * DELETE /api/anemometers/{id} — delete.
     */
    public function destroy(string $id): Response
    {
        $anemometer = $this->anemometers->findOrFail($id);
        $this->anemometers->delete($anemometer);

        return response()->noContent();
    }

    /**
     * GET /api/anemometers/recent-readings
     *
     * Port of the Django @action: annotates each anemometer with the average
     * speed over the past 24h and 7d, and attaches its 5 most-recent readings.
     *
     * @return array<string, mixed>
     */
    public function recentReadings(Request $request): array
    {
        return DrfPagination::shape(
            $this->anemometers->paginateWithRecentAggregates(),
            fn (Anemometer $a) => (new RecentReadingsAnemometerResource($a))->resolve(),
        );
    }
}
