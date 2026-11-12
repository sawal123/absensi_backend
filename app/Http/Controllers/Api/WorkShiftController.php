<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\AuthorizesRoles;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreWorkShiftRequest;
use App\Http\Resources\WorkShiftResource;
use App\Models\WorkShift;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class WorkShiftController extends Controller
{
    use AuthorizesRoles;

    public function index(): AnonymousResourceCollection
    {
        return WorkShiftResource::collection(
            WorkShift::query()->orderBy('start_time')->orderBy('name')->get(),
        );
    }

    public function store(StoreWorkShiftRequest $request): JsonResponse
    {
        $this->ensureManager($request);

        $data = $request->validated();
        $data['office'] = $data['office'] ?? '';
        $data['cross_day'] = $data['cross_day'] ?? false;
        $data['is_active'] = $data['is_active'] ?? true;
        $data['color'] = $data['color'] ?? $this->nextColor();

        $shift = WorkShift::query()->create($data);

        return (new WorkShiftResource($shift))->response()->setStatusCode(201);
    }

    public function update(StoreWorkShiftRequest $request, WorkShift $shift): WorkShiftResource
    {
        $this->ensureManager($request);

        $shift->fill($request->validated())->save();

        return new WorkShiftResource($shift->refresh());
    }

    public function destroy(Request $request, WorkShift $shift): JsonResponse
    {
        $this->ensureManager($request);

        $shift->delete();

        return response()->json(['data' => ['message' => 'Shift berhasil dihapus.']]);
    }

    private function nextColor(): int
    {
        foreach (WorkShift::COLOR_PALETTE as $color) {
            if (! WorkShift::query()->where('color', $color)->exists()) {
                return $color;
            }
        }

        $count = WorkShift::query()->count();

        return WorkShift::COLOR_PALETTE[$count % count(WorkShift::COLOR_PALETTE)];
    }
}
