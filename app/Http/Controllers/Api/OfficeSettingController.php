<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\AuthorizesRoles;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateOfficeSettingRequest;
use App\Http\Resources\OfficeSettingResource;
use App\Models\OfficeLocation;
use App\Models\OfficeSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OfficeSettingController extends Controller
{
    use AuthorizesRoles;

    public function show(Request $request): JsonResponse
    {
        $settings = OfficeSetting::current();
        $location = $this->resolveLocation($request);

        return response()->json([
            'data' => $this->payload($request, $settings, $location),
        ]);
    }

    public function update(UpdateOfficeSettingRequest $request): JsonResponse
    {
        $this->ensureAdmin($request);

        $data = $request->validated();
        $locationId = $data['office_location_id'] ?? null;
        unset($data['office_location_id']);

        $settings = OfficeSetting::current();
        $settings->fill(array_intersect_key($data, array_flip($settings->getFillable())))->save();

        $location = $locationId !== null
            ? OfficeLocation::query()->find($locationId)
            : OfficeLocation::defaultLocation();

        if ($location !== null) {
            $location->fill(array_filter([
                'name' => $data['office_name'] ?? null,
                'latitude' => $data['latitude'] ?? null,
                'longitude' => $data['longitude'] ?? null,
                'radius_meters' => $data['radius_meters'] ?? null,
                'timezone' => $data['timezone'] ?? null,
            ], fn ($value) => $value !== null))->save();
        }

        return response()->json([
            'data' => $this->payload($request, $settings->refresh(), $location?->refresh()),
        ]);
    }

    private function resolveLocation(Request $request): ?OfficeLocation
    {
        if ($request->filled('office_location_id')) {
            $this->ensureManager($request);

            return OfficeLocation::query()->find((int) $request->query('office_location_id'));
        }

        return $request->user()->effectiveOfficeLocation();
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(
        Request $request,
        OfficeSetting $settings,
        ?OfficeLocation $location,
    ): array {
        $data = (new OfficeSettingResource($settings))->resolve($request);

        if ($location !== null) {
            $data['office_location_id'] = $location->id;
            $data['office_name'] = $location->name;
            $data['latitude'] = $location->latitude;
            $data['longitude'] = $location->longitude;
            $data['radius_meters'] = $location->radius_meters;
            $data['timezone'] = $location->timezone;
        }

        return $data;
    }
}
