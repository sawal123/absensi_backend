<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tenant extends Model
{
    public const STATUS_TRIAL = 'trial';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_SUSPENDED = 'suspended';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'name',
        'code',
        'domain',
        'plan',
        'status',
        'trial_ends_at',
        'subscription_ends_at',
        'max_employees',
        'features',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'trial_ends_at' => 'datetime',
            'subscription_ends_at' => 'datetime',
            'max_employees' => 'integer',
            'features' => 'array',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function isActive(): bool
    {
        if (! in_array($this->status, [self::STATUS_TRIAL, self::STATUS_ACTIVE], true)) {
            return false;
        }

        if ($this->status === self::STATUS_TRIAL && $this->trial_ends_at?->isPast()) {
            return false;
        }

        if ($this->subscription_ends_at?->isPast()) {
            return false;
        }

        return true;
    }
}
