<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attendance extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'user_id',
        'date',
        'check_in_at',
        'check_in_lat',
        'check_in_lng',
        'check_in_photo',
        'check_in_distance',
        'check_out_at',
        'check_out_lat',
        'check_out_lng',
        'check_out_photo',
        'check_out_distance',
        'is_mocked',
        'liveness_passed',
        'face_match_score',
        'device_id',
        'ip_address',
        'user_agent',
        'status',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'check_in_at' => 'datetime',
            'check_out_at' => 'datetime',
            'check_in_lat' => 'float',
            'check_in_lng' => 'float',
            'check_out_lat' => 'float',
            'check_out_lng' => 'float',
            'check_in_distance' => 'integer',
            'check_out_distance' => 'integer',
            'is_mocked' => 'boolean',
            'liveness_passed' => 'boolean',
            'face_match_score' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function hasCheckedIn(): bool
    {
        return $this->check_in_at !== null;
    }

    public function hasCheckedOut(): bool
    {
        return $this->check_out_at !== null;
    }

    public function workDurationMinutes(): ?int
    {
        if ($this->check_in_at === null || $this->check_out_at === null) {
            return null;
        }

        return (int) $this->check_in_at->diffInMinutes($this->check_out_at);
    }
}
