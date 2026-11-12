<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\AuthorizesRoles;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpsertSalaryProfileRequest;
use App\Http\Resources\SalaryProfileResource;
use App\Models\SalaryProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SalaryProfileController extends Controller
{
    use AuthorizesRoles;

    public function index(Request $request): AnonymousResourceCollection
    {
        $actor = $request->user();

        $query = SalaryProfile::query()->with('employee');

        if ($actor->isManager()) {
            if ($request->filled('employee_id')) {
                $query->where('employee_id', (int) $request->query('employee_id'));
            }
        } else {
            $query->where('employee_id', $actor->id);
        }

        return SalaryProfileResource::collection($query->get());
    }

    public function show(Request $request, SalaryProfile $salaryProfile): SalaryProfileResource
    {
        $this->ensureSelfOrManager($request, $salaryProfile->employee_id);

        return new SalaryProfileResource($salaryProfile->load('employee'));
    }

    public function upsert(UpsertSalaryProfileRequest $request): JsonResponse
    {
        $this->ensureManager($request);
        $data = $request->validated();

        $profile = SalaryProfile::query()->updateOrCreate(
            ['employee_id' => $data['employee_id']],
            [
                'base_salary' => $data['base_salary'],
                'overtime_rate' => $data['overtime_rate'] ?? 0,
                'ptkp_status' => $data['ptkp_status'] ?? 'TK/0',
                'npwp' => $data['npwp'] ?? '',
            ],
        );

        return (new SalaryProfileResource($profile->load('employee')))
            ->response()
            ->setStatusCode($profile->wasRecentlyCreated ? 201 : 200);
    }

    public function destroy(Request $request, SalaryProfile $salaryProfile): JsonResponse
    {
        $this->ensureManager($request);

        $salaryProfile->delete();

        return response()->json(['data' => ['message' => 'Profil gaji dihapus.']]);
    }
}
