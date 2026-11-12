<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\AuthorizesRoles;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class UserController extends Controller
{
    use AuthorizesRoles;

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->ensureManager($request);

        $query = User::query();

        if ($role = $request->query('role')) {
            $query->where('role', $role);
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($search = trim((string) $request->query('search', ''))) {
            $query->where(function ($builder) use ($search) {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('nip', 'like', "%{$search}%");
            });
        }

        return UserResource::collection($query->orderBy('name')->get());
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $this->ensureManager($request);

        $tenant = TenantContext::get();
        if ($tenant !== null) {
            $employeeCount = User::query()->employees()->count();
            abort_if(
                $employeeCount >= $tenant->max_employees,
                422,
                'Kuota karyawan perusahaan sudah penuh. Hubungi admin platform.',
            );
        }

        $data = $request->validated();
        $data['email'] = mb_strtolower($data['email']);
        $data['role'] = $data['role'] ?? User::ROLE_EMPLOYEE;
        $data['is_active'] = $data['is_active'] ?? true;

        $user = User::query()->create($data);

        return (new UserResource($user))->response()->setStatusCode(201);
    }

    public function show(Request $request, User $user): UserResource
    {
        $this->ensureSelfOrManager($request, $user->id);

        return new UserResource($user);
    }

    public function update(UpdateUserRequest $request, User $user): UserResource
    {
        $actor = $request->user();
        $this->ensureSelfOrManager($request, $user->id);

        $data = $request->validated();

        if (! $actor->isManager()) {
            // Karyawan hanya boleh mengubah profil dasarnya sendiri.
            $data = array_intersect_key($data, array_flip(['name', 'phone', 'avatar_url']));
        }

        if ($actor->isManager() && ! $actor->isAdmin()) {
            unset($data['role']);
        }

        if (! empty($data['email'])) {
            $data['email'] = mb_strtolower($data['email']);
        }

        if (($data['password'] ?? null) === null) {
            unset($data['password']);
        }

        $user->fill($data)->save();

        return new UserResource($user->refresh());
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        $admin = $this->ensureAdmin($request);

        abort_if($admin->id === $user->id, 422, 'Anda tidak dapat menghapus akun sendiri.');

        $user->delete();

        return response()->json(['data' => ['message' => 'Akun berhasil dihapus.']]);
    }
}
