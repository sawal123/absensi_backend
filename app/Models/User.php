<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use App\Support\TenantContext;
use Laravel\Sanctum\HasApiTokens;

#[Fillable([
    'name',
    'email',
    'password',
    'role',
    'tenant_id',
    'nip',
    'position',
    'team',
    'office_location_id',
    'phone',
    'avatar_url',
    'face_photo_path',
    'face_hash',
    'face_enrolled_at',
    'last_login_at',
    'is_active',
    'joined_at',
])]
#[Hidden(['password', 'remember_token', 'face_hash'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use BelongsToTenant, HasApiTokens, HasFactory, Notifiable;

    public const ROLE_ADMIN = 'admin';

    public const ROLE_SUPER_ADMIN = 'super_admin';

    public const ROLE_SUPERVISOR = 'supervisor';

    public const ROLE_EMPLOYEE = 'employee';

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'joined_at' => 'date',
            'face_enrolled_at' => 'datetime',
            'last_login_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(function (User $user) {
            if ($user->face_photo_path !== null) {
                Storage::disk('local')->delete($user->face_photo_path);
            }

            foreach ($user->attendances()->get() as $attendance) {
                foreach (['check_in_photo', 'check_out_photo'] as $field) {
                    $path = $attendance->{$field};
                    if (is_string($path) && $path !== '') {
                        Storage::disk('local')->delete($path);
                    }
                }
            }

            foreach ($user->leaveRequests()->get() as $leave) {
                self::deleteStoredFile($leave->attachment_url);
            }

            foreach ($user->attendanceCorrections()->get() as $correction) {
                self::deleteStoredFile($correction->evidence_url);
            }

            // Jangan menyisakan data pribadi di audit log.
            AuditLog::query()
                ->where('user_id', $user->id)
                ->update(['user_id' => null, 'payload' => null]);
        });
    }

    private static function deleteStoredFile(?string $url): void
    {
        if ($url === null || $url === '') {
            return;
        }

        $path = parse_url($url, PHP_URL_PATH);
        if (! is_string($path) || ! str_contains($path, '/storage/')) {
            return;
        }

        $relative = ltrim(substr($path, strpos($path, '/storage/') + 9), '/');
        if ($relative !== '') {
            Storage::disk('public')->delete($relative);
        }
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class, 'employee_id');
    }

    public function leaveBalances(): HasMany
    {
        return $this->hasMany(LeaveBalance::class, 'employee_id');
    }

    public function attendanceCorrections(): HasMany
    {
        return $this->hasMany(AttendanceCorrection::class, 'employee_id');
    }

    public function shiftAssignments(): HasMany
    {
        return $this->hasMany(ShiftAssignment::class, 'employee_id');
    }

    public function overtimeRequests(): HasMany
    {
        return $this->hasMany(OvertimeRequest::class, 'employee_id');
    }

    public function salaryProfile(): HasOne
    {
        return $this->hasOne(SalaryProfile::class, 'employee_id');
    }

    public function salaryAllowances(): HasMany
    {
        return $this->hasMany(SalaryAllowance::class, 'employee_id')->orderBy('sort_order');
    }

    public function payrollSlips(): HasMany
    {
        return $this->hasMany(PayrollSlip::class, 'employee_id');
    }

    public function officeLocation(): BelongsTo
    {
        return $this->belongsTo(OfficeLocation::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(UserNotification::class)->latest();
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === self::ROLE_SUPER_ADMIN;
    }

    public function isSupervisor(): bool
    {
        return $this->role === self::ROLE_SUPERVISOR;
    }

    /** Admin dan atasan boleh melihat serta meninjau pengajuan tim. */
    public function isManager(): bool
    {
        if ($this->isSuperAdmin()) {
            return TenantContext::id() !== null;
        }

        return $this->isAdmin() || $this->isSupervisor();
    }

    public function hasEnrolledFace(): bool
    {
        return $this->face_hash !== null && $this->face_photo_path !== null;
    }

    public function effectiveOfficeLocation(): ?OfficeLocation
    {
        if ($this->officeLocation !== null && $this->officeLocation->is_active) {
            return $this->officeLocation;
        }

        return OfficeLocation::defaultLocation();
    }

    public function initials(): string
    {
        $parts = preg_split('/\s+/', trim((string) $this->name)) ?: [];
        $initials = '';
        foreach ($parts as $part) {
            if ($part !== '') {
                $initials .= mb_substr($part, 0, 1);
            }
        }

        return mb_strtoupper(mb_substr($initials, 0, 2));
    }

    public function scopeEmployees(Builder $query): Builder
    {
        return $query->where('role', self::ROLE_EMPLOYEE);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
