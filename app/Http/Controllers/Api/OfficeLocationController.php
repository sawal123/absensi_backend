<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\AuthorizesRoles;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOfficeLocationRequest;
use App\Http\Resources\OfficeLocationResource;
use App\Models\OfficeLocation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class OfficeLocationController extends Controller
{
    use AuthorizesRoles;

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->ensureManager($request);

        return OfficeLocationResource::collection(
            OfficeLocation::query()
                ->withCount('users')
                ->when(! $request->boolean('include_inactive'), fn ($query) => $query->where('is_active', true))
                ->orderByDesc('is_default')
                ->orderBy('name')
                ->get(),
        );
    }

    public function store(StoreOfficeLocationRequest $request): JsonResponse
    {
        $this->ensureAdmin($request);

        $data = $request->validated();
        $makeDefault = (bool) ($data['is_default'] ?? false);

        $location = DB::transaction(function () use ($data, $makeDefault) {
            if ($makeDefault) {
                OfficeLocation::query()->update(['is_default' => false]);
            }

            return OfficeLocation::query()->create([
                'name' => $data['name'],
                'address' => $data['address'] ?? '',
                'latitude' => $data['latitude'],
                'longitude' => $data['longitude'],
                'radius_meters' => $data['radius_meters'] ?? 100,
                'timezone' => $data['timezone'] ?? 'Asia/Jakarta',
                'is_default' => $makeDefault,
                'is_active' => $data['is_active'] ?? true,
            ]);
        });

        return (new OfficeLocationResource($location))
            ->response()
            ->setStatusCode(201);
    }

    public function update(StoreOfficeLocationRequest $request, OfficeLocation $officeLocation): OfficeLocationResource
    {
        $this->ensureAdmin($request);

        $data = $request->validated();

        DB::transaction(function () use ($officeLocation, $data) {
            if (($data['is_default'] ?? false) === true) {
                OfficeLocation::query()->whereKeyNot($officeLocation->id)->update(['is_default' => false]);
            }

            $officeLocation->fill($data)->save();
        });

        return new OfficeLocationResource($officeLocation->refresh());
    }

    public function destroy(Request $request, OfficeLocation $officeLocation): JsonResponse
    {
        $this->ensureAdmin($request);

        abort_if(
            OfficeLocation::query()->count() <= 1,
            422,
            'Minimal satu cabang harus tetap ada.',
        );

        $wasDefault = $officeLocation->is_default;
        $officeLocation->delete();

        if ($wasDefault) {
            $next = OfficeLocation::query()->where('is_active', true)->first();
            $next?->forceFill(['is_default' => true])->save();
        }

        return response()->json(['data' => ['message' => 'Cabang dihapus.']]);
    }
}
