<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ChangePasswordRequest;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use App\Models\OfficeSetting;
use App\Models\User;
use App\Support\FaceMatcher;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::query()
            ->where('email', mb_strtolower(trim((string) $request->input('email'))))
            ->first();

        if ($user === null || ! Hash::check((string) $request->input('password'), $user->password)) {
            throw ValidationException::withMessages([
                'email' => 'Email atau password salah.',
            ]);
        }

        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'email' => 'Akun Anda dinonaktifkan. Hubungi admin.',
            ]);
        }

        $tenant = $user->tenant;
        if (! $user->isSuperAdmin()) {
            if ($tenant === null) {
                throw ValidationException::withMessages([
                    'email' => 'Akun tidak terhubung ke perusahaan.',
                ]);
            }

            if (! $tenant->isActive()) {
                throw ValidationException::withMessages([
                    'email' => 'Langganan perusahaan tidak aktif. Hubungi admin.',
                ]);
            }

            TenantContext::set($tenant);
        }

        $settings = $tenant !== null ? OfficeSetting::current() : null;
        $deviceId = trim((string) ($request->input('device_id') ?? ''));
        $deviceName = (string) ($request->input('device_name') ?: 'mobile');

        if (($settings?->device_binding_enabled ?? false) && $deviceId !== '') {
            // Satu akun hanya boleh aktif pada satu perangkat.
            $user->tokens()
                ->where(function ($query) use ($deviceId) {
                    $query->whereNull('device_id')->orWhere('device_id', '!=', $deviceId);
                })
                ->delete();
        }

        $newToken = $user->createToken($deviceName, ['*'], now()->addDays(30));
        if ($deviceId !== '') {
            $newToken->accessToken->forceFill(['device_id' => $deviceId])->save();
        }

        $user->forceFill(['last_login_at' => now()])->save();

        return response()->json([
            'data' => [
                'token' => $newToken->plainTextToken,
                'token_type' => 'Bearer',
                'server_time' => now()->toIso8601String(),
                'device_binding' => $settings?->device_binding_enabled ?? false,
                'face_enrolled' => $user->hasEnrolledFace(),
                'user' => new UserResource($user->load(['tenant', 'officeLocation'])),
                'tenant' => $tenant
                    ? (new \App\Http\Resources\TenantResource($tenant))->resolve($request)
                    : null,
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json(['data' => ['message' => 'Berhasil keluar.']]);
    }

    public function me(Request $request): UserResource
    {
        return new UserResource($request->user()->load(['tenant', 'officeLocation']));
    }

    public function serverTime(): JsonResponse
    {
        return response()->json([
            'data' => [
                'server_time' => now()->toIso8601String(),
                'timezone' => OfficeSetting::current()->timezone,
            ],
        ]);
    }

    public function updateProfile(UpdateProfileRequest $request): UserResource
    {
        $user = $request->user();
        $user->fill($request->validated())->save();

        return new UserResource($user->load(['tenant', 'officeLocation']));
    }

    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $user = $request->user();

        if (! Hash::check((string) $request->input('current_password'), $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => 'Password lama tidak sesuai.',
            ]);
        }

        $user->forceFill(['password' => (string) $request->input('password')])->save();

        $currentTokenId = $user->currentAccessToken()?->id;
        if ($currentTokenId !== null) {
            $user->tokens()->whereKeyNot($currentTokenId)->delete();
        }

        return response()->json(['data' => ['message' => 'Password berhasil diperbarui.']]);
    }

    /**
     * Daftarkan foto referensi wajah. Foto selfie absensi nanti dibandingkan
     * dengan hash gambar referensi ini.
     */
    public function enrollFace(Request $request): JsonResponse
    {
        $request->validate([
            'photo' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
        ], [
            'photo.required' => 'Foto wajah wajib diambil.',
            'photo.mimes' => 'Foto wajah harus berformat JPG, PNG, atau WEBP.',
            'photo.max' => 'Ukuran foto maksimal 10 MB.',
        ]);

        /** @var User $user */
        $user = $request->user();
        $file = $request->file('photo');
        $hash = FaceMatcher::hashFile($file->getRealPath());

        if ($hash === null) {
            throw ValidationException::withMessages([
                'photo' => 'Foto wajah tidak dapat dibaca. Coba ambil ulang.',
            ]);
        }

        if ($user->face_photo_path !== null) {
            Storage::disk('local')->delete($user->face_photo_path);
        }

        $path = $file->store(
            'faces/'.(TenantContext::id() ?? 'platform').'/'.$user->id,
            'local',
        );
        $user->forceFill([
            'face_photo_path' => $path,
            'face_hash' => $hash,
            'face_enrolled_at' => now(),
        ])->save();

        return response()->json([
            'data' => [
                'face_enrolled' => true,
                'face_enrolled_at' => $user->face_enrolled_at?->toIso8601String(),
            ],
        ]);
    }

    public function deleteFace(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->face_photo_path !== null) {
            Storage::disk('local')->delete($user->face_photo_path);
        }

        $user->forceFill([
            'face_photo_path' => null,
            'face_hash' => null,
            'face_enrolled_at' => null,
        ])->save();

        return response()->json([
            'data' => ['message' => 'Data wajah dihapus.', 'face_enrolled' => false],
        ]);
    }

    /**
     * Hapus akun beserta seluruh data pribadi terkait (absensi, cuti,
     * perbaikan, lembur, payroll, wajah, dan token).
     */
    public function deleteAccount(Request $request): JsonResponse
    {
        $request->validate([
            'password' => ['required', 'string'],
        ], [
            'password.required' => 'Password wajib diisi untuk menghapus akun.',
        ]);

        /** @var User $user */
        $user = $request->user();

        if (! Hash::check((string) $request->input('password'), $user->password)) {
            throw ValidationException::withMessages([
                'password' => 'Password tidak sesuai.',
            ]);
        }

        if (
            $user->isAdmin()
            && User::query()->where('role', User::ROLE_ADMIN)->count() <= 1
        ) {
            throw ValidationException::withMessages([
                'password' => 'Admin terakhir tidak dapat menghapus akunnya.',
            ]);
        }

        if ($user->face_photo_path !== null) {
            Storage::disk('local')->delete($user->face_photo_path);
        }

        $user->tokens()->delete();
        // Cegah middleware audit menulis user_id yang sudah terhapus.
        $request->setUserResolver(fn () => null);
        $user->delete();

        return response()->json([
            'data' => ['message' => 'Akun dan seluruh data terkait telah dihapus.'],
        ]);
    }
}
