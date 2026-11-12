<?php

namespace App\Support;

use App\Models\AttendanceCorrection;
use App\Models\LeaveRequest;
use App\Models\User;
use Illuminate\Support\Carbon;

final class CodeGenerator
{
    public static function leaveRequest(User $employee): string
    {
        return self::make('CT', $employee->nip ?? (string) $employee->id, Carbon::now());
    }

    public static function correction(User $employee): string
    {
        return self::make('PA', $employee->nip ?? (string) $employee->id, Carbon::now());
    }

    private static function make(string $prefix, string $nip, Carbon $date): string
    {
        $compact = $date->format('ymd');
        $suffix = $nip !== '' ? $nip : '0000';

        return sprintf('%s-%s-%s', $prefix, $compact, $suffix);
    }

    /**
     * Pastikan kode unik bila ada dua pengajuan pada detik yang sama.
     */
    public static function uniqueLeaveCode(string $candidate): string
    {
        $code = $candidate;
        $counter = 1;
        while (LeaveRequest::query()->where('code', $code)->exists()) {
            $code = $candidate.'-'.$counter++;
        }

        return $code;
    }

    public static function uniqueCorrectionCode(string $candidate): string
    {
        $code = $candidate;
        $counter = 1;
        while (AttendanceCorrection::query()->where('code', $code)->exists()) {
            $code = $candidate.'-'.$counter++;
        }

        return $code;
    }
}
