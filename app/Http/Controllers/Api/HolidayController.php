<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\AuthorizesRoles;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreHolidayRequest;
use App\Http\Resources\HolidayResource;
use App\Models\Holiday;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;

class HolidayController extends Controller
{
    use AuthorizesRoles;

    public function index(Request $request): AnonymousResourceCollection
    {
        $year = (int) $request->query('year', now()->year);
        $locationId = $request->filled('office_location_id')
            ? (int) $request->query('office_location_id')
            : null;

        return HolidayResource::collection(
            Holiday::query()
                ->when($locationId !== null, function ($query) use ($locationId) {
                    $query->where(function ($builder) use ($locationId) {
                        $builder->whereNull('office_location_id')
                            ->orWhere('office_location_id', $locationId);
                    });
                })
                ->whereYear('date', $year)
                ->orderBy('date')
                ->get(),
        );
    }

    public function store(StoreHolidayRequest $request): JsonResponse
    {
        $this->ensureAdmin($request);

        $data = $request->validated();
        $this->ensureUnique($data);

        $holiday = Holiday::query()->create([
            'date' => $data['date'],
            'name' => $data['name'],
            'is_national' => $data['is_national'] ?? true,
            'office_location_id' => $data['office_location_id'] ?? null,
        ]);

        return (new HolidayResource($holiday))->response()->setStatusCode(201);
    }

    public function update(StoreHolidayRequest $request, Holiday $holiday): HolidayResource
    {
        $this->ensureAdmin($request);

        $data = $request->validated();
        $this->ensureUnique($data, $holiday->id);

        $holiday->fill([
            'date' => $data['date'],
            'name' => $data['name'],
            'is_national' => $data['is_national'] ?? true,
            'office_location_id' => $data['office_location_id'] ?? null,
        ])->save();

        return new HolidayResource($holiday->refresh());
    }

    public function destroy(Request $request, Holiday $holiday): JsonResponse
    {
        $this->ensureAdmin($request);

        $holiday->delete();

        return response()->json(['data' => ['message' => 'Hari libur dihapus.']]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function ensureUnique(array $data, ?int $ignoreId = null): void
    {
        $exists = Holiday::query()
            ->whereDate('date', $data['date'])
            ->where('office_location_id', $data['office_location_id'] ?? null)
            ->when($ignoreId !== null, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'date' => 'Hari libur pada tanggal dan cabang tersebut sudah ada.',
            ]);
        }
    }
}
