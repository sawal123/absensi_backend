<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkShift extends Model
{
    use BelongsToTenant;

    public const COLOR_PALETTE = [
        0xFF2563EB,
        0xFF15803D,
        0xFFB45309,
        0xFF7C3AED,
        0xFFDB2777,
        0xFF0891B2,
        0xFFDC2626,
        0xFF4F46E5,
    ];

    protected $fillable = [
        'name',
        'start_time',
        'end_time',
        'office',
        'break_label',
        'cross_day',
        'is_active',
        'color',
    ];

    protected function casts(): array
    {
        return [
            'cross_day' => 'boolean',
            'is_active' => 'boolean',
            'color' => 'integer',
        ];
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(ShiftAssignment::class, 'shift_id');
    }

    public function durationMinutes(): int
    {
        $start = self::minutesOf($this->start_time);
        $end = self::minutesOf($this->end_time);
        $total = $end - $start;
        if ($this->cross_day || $total <= 0) {
            $total += 24 * 60;
        }

        return $total;
    }

    public static function minutesOf(string $value): int
    {
        [$hour, $minute] = array_pad(explode(':', $value), 2, '0');

        return ((int) $hour) * 60 + ((int) $minute);
    }
}
