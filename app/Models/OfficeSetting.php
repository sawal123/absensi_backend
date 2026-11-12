<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;

use Illuminate\Database\Eloquent\Model;

class OfficeSetting extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'office_name',
        'latitude',
        'longitude',
        'radius_meters',
        'work_start',
        'work_end',
        'early_check_in_minutes',
        'late_tolerance_minutes',
        'face_match_required',
        'face_match_threshold',
        'overtime_requires_approval',
        'device_binding_enabled',
        'selfie_required',
        'mock_location_blocked',
        'timezone',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'radius_meters' => 'integer',
            'early_check_in_minutes' => 'integer',
            'late_tolerance_minutes' => 'integer',
            'face_match_required' => 'boolean',
            'face_match_threshold' => 'integer',
            'overtime_requires_approval' => 'boolean',
            'device_binding_enabled' => 'boolean',
            'selfie_required' => 'boolean',
            'mock_location_blocked' => 'boolean',
        ];
    }

    public static function current(): self
    {
        return static::query()->firstOrCreate([], [
            'office_name' => 'Kantor Pusat',
            'latitude' => -6.2000000,
            'longitude' => 106.8166660,
            'radius_meters' => 100,
            'work_start' => '08:00',
            'work_end' => '17:00',
            'early_check_in_minutes' => 60,
            'late_tolerance_minutes' => 10,
            'face_match_required' => false,
            'face_match_threshold' => 12,
            'overtime_requires_approval' => true,
            'device_binding_enabled' => true,
            'selfie_required' => true,
            'mock_location_blocked' => true,
            'timezone' => 'Asia/Jakarta',
        ]);
    }
}
