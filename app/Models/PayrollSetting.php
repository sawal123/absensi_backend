<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;

use Illuminate\Database\Eloquent\Model;

class PayrollSetting extends Model
{
    use BelongsToTenant;

    public const DEFAULT_PTKP = [
        'TK/0' => 54000000,
        'TK/1' => 58500000,
        'TK/2' => 63000000,
        'TK/3' => 67500000,
        'K/0' => 58500000,
        'K/1' => 63000000,
        'K/2' => 67500000,
        'K/3' => 72000000,
    ];

    public const DEFAULT_BRACKETS = [
        ['limit' => 60000000, 'rate' => 5],
        ['limit' => 250000000, 'rate' => 15],
        ['limit' => 500000000, 'rate' => 25],
        ['limit' => 5000000000, 'rate' => 30],
        ['limit' => null, 'rate' => 35],
    ];

    protected $fillable = [
        'overtime_enabled',
        'overtime_min_minutes',
        'ptkp',
        'pph21_brackets',
    ];

    protected function casts(): array
    {
        return [
            'overtime_enabled' => 'boolean',
            'overtime_min_minutes' => 'integer',
            'ptkp' => 'array',
            'pph21_brackets' => 'array',
        ];
    }

    public static function current(): self
    {
        return static::query()->firstOrCreate([], [
            'overtime_enabled' => true,
            'overtime_min_minutes' => 30,
            'ptkp' => self::DEFAULT_PTKP,
            'pph21_brackets' => self::DEFAULT_BRACKETS,
        ]);
    }

    public function ptkpFor(string $status): int
    {
        return (int) ($this->ptkp[$status] ?? $this->ptkp['TK/0'] ?? 54000000);
    }
}
