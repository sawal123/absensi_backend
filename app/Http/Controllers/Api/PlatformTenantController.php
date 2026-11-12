<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TenantResource;
use App\Http\Resources\UserResource;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantProvisioner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PlatformTenantController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return TenantResource::collection(
            Tenant::query()->withCount('users')->orderBy('name')->get(),
        );
    }

    public function store(
        Request $request,
        TenantProvisioner $provisioner,
    ): JsonResponse {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'code' => ['required', 'string', 'max:40', 'alpha_dash', 'unique:tenants,code'],
            'domain' => ['nullable', 'string', 'max:190', 'unique:tenants,domain'],
            'plan' => ['nullable', 'string', 'max:30'],
            'trial_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'max_employees' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'admin_name' => ['required', 'string', 'max:120'],
            'admin_email' => ['required', 'email', 'max:180', 'unique:users,email'],
            'admin_password' => ['required', 'string', 'min:6', 'max:100'],
        ]);

        $trialDays = (int) ($data['trial_days'] ?? 14);

        [$tenant, $admin] = DB::transaction(function () use ($data, $trialDays, $provisioner) {
            $tenant = Tenant::query()->create([
                'name' => $data['name'],
                'code' => $data['code'],
                'domain' => $data['domain'] ?? null,
                'plan' => $data['plan'] ?? 'basic',
                'status' => $trialDays > 0 ? Tenant::STATUS_TRIAL : Tenant::STATUS_ACTIVE,
                'trial_ends_at' => $trialDays > 0 ? now()->addDays($trialDays) : null,
                'max_employees' => $data['max_employees'] ?? 50,
            ]);

            $admin = $provisioner->provision($tenant, [
                'name' => $data['admin_name'],
                'email' => $data['admin_email'],
                'password' => $data['admin_password'],
            ]);

            return [$tenant, $admin];
        });

        return response()->json([
            'data' => [
                'tenant' => (new TenantResource($tenant->loadCount('users')))->resolve($request),
                'admin' => (new UserResource($admin->load(['tenant', 'officeLocation'])))->resolve($request),
            ],
        ], 201);
    }

    public function show(Request $request, Tenant $tenant): TenantResource
    {
        return new TenantResource($tenant->loadCount('users'));
    }

    public function update(Request $request, Tenant $tenant): TenantResource
    {
        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:120'],
            'domain' => [
                'nullable',
                'string',
                'max:190',
                Rule::unique('tenants', 'domain')->ignore($tenant->id),
            ],
            'plan' => ['sometimes', 'required', 'string', 'max:30'],
            'status' => ['sometimes', 'required', Rule::in([
                Tenant::STATUS_TRIAL,
                Tenant::STATUS_ACTIVE,
                Tenant::STATUS_SUSPENDED,
                Tenant::STATUS_CANCELLED,
            ])],
            'trial_ends_at' => ['nullable', 'date'],
            'subscription_ends_at' => ['nullable', 'date'],
            'max_employees' => ['sometimes', 'required', 'integer', 'min:1', 'max:100000'],
            'features' => ['nullable', 'array'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $tenant->fill($data)->save();

        return new TenantResource($tenant->refresh()->loadCount('users'));
    }
}
