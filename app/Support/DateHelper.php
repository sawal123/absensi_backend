<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

final class DateHelper
{
    public static function dateOnly(CarbonInterface|string $value): Carbon
    {
        return Carbon::parse($value)->startOfDay();
    }

    public static function endOfMonth(CarbonInterface $value): Carbon
    {
        return Carbon::parse($value)->endOfMonth()->startOfDay();
    }

    /**
     * Jumlah hari Senin-Jumat dalam rentang inklusif, dikurangi hari libur.
     *
     * @param  array<int, string>  $holidays  daftar tanggal Y-m-d
     */
    public static function workingDaysBetween(
        CarbonInterface $start,
        CarbonInterface $end,
        array $holidays = [],
    ): int {
        $holidayLookup = array_flip($holidays);
        $total = 0;
        for ($day = Carbon::parse($start); $day->lte($end); $day->addDay()) {
            if ($day->isWeekend()) {
                continue;
            }
            if (isset($holidayLookup[$day->toDateString()])) {
                continue;
            }
            $total++;
        }

        return $total;
    }

    /**
     * @param  array<int, string>  $holidays
     */
    public static function workingDays(string $start, string $end, array $holidays = []): int
    {
        return self::workingDaysBetween(
            Carbon::parse($start)->startOfDay(),
            Carbon::parse($end)->startOfDay(),
            $holidays,
        );
    }

    public static function atTime(CarbonInterface $date, string $time): Carbon
    {
        [$hour, $minute] = array_pad(explode(':', $time), 2, '0');

        return Carbon::parse($date)->setTime((int) $hour, (int) $minute);
    }

    /**
     * Daftar hari libur efektif untuk sebuah kantor pada rentang tanggal.
     *
     * @return array<int, string>
     */
    public static function holidayDates(
        ?int $officeLocationId,
        CarbonInterface $from,
        CarbonInterface $to,
    ): array {
        return \App\Models\Holiday::query()
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->where(function ($query) use ($officeLocationId) {
                $query->whereNull('office_location_id');
                if ($officeLocationId !== null) {
                    $query->orWhere('office_location_id', $officeLocationId);
                }
            })
            ->pluck('date')
            ->map(fn ($date) => Carbon::parse($date)->toDateString())
            ->all();
    }
}
