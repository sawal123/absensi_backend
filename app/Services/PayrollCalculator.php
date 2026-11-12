<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\LeaveRequest;
use App\Models\OfficeSetting;
use App\Models\OvertimeRequest;
use App\Models\PayrollDeductionRule;
use App\Models\PayrollSetting;
use App\Models\SalaryAllowance;
use App\Models\SalaryProfile;
use App\Models\ShiftAssignment;
use App\Models\User;
use App\Support\DateHelper;
use Illuminate\Support\Carbon;

class PayrollCalculator
{
    /**
     * Hitung slip gaji satu karyawan untuk periode tertentu.
     *
     * @return array<string, mixed>
     */
    public function compute(User $employee, int $year, int $month, ?Carbon $asOf = null): array
    {
        $profile = $employee->salaryProfile;
        $allowances = $employee->salaryAllowances()->get();
        $rules = PayrollDeductionRule::query()->orderBy('sort_order')->get();
        $settings = PayrollSetting::current();
        $office = OfficeSetting::current();

        $periodStart = Carbon::create($year, $month, 1)->startOfDay();
        $periodEnd = DateHelper::endOfMonth($periodStart);
        $cutoff = ($asOf ?? Carbon::now())->copy()->startOfDay();
        $absenceEnd = $periodEnd->gt($cutoff) ? $cutoff : $periodEnd;

        $holidays = DateHelper::holidayDates(
            $employee->office_location_id,
            $periodStart,
            $periodEnd,
        );
        $holidayLookup = array_flip($holidays);

        $workingDays = DateHelper::workingDaysBetween($periodStart, $periodEnd, $holidays);
        $dailyWage = $workingDays === 0 ? 0 : (int) round(($profile?->base_salary ?? 0) / $workingDays);

        $attendance = Attendance::query()
            ->where('user_id', $employee->id)
            ->whereBetween('date', [$periodStart->toDateString(), $periodEnd->toDateString()])
            ->get();

        $assignments = ShiftAssignment::query()
            ->with('shift')
            ->where('employee_id', $employee->id)
            ->whereBetween('date', [$periodStart->toDateString(), $periodEnd->toDateString()])
            ->get()
            ->keyBy(fn (ShiftAssignment $item) => $item->date->toDateString());

        $approvedLeaves = LeaveRequest::query()
            ->with('leaveType')
            ->where('employee_id', $employee->id)
            ->where('status', LeaveRequest::STATUS_APPROVED)
            ->where('start_date', '<=', $periodEnd->toDateString())
            ->where('end_date', '>=', $periodStart->toDateString())
            ->get();

        $presentDates = [];
        $lateCount = 0;
        $lateMinutes = 0;
        $overtimeMinutes = 0;

        foreach ($attendance as $record) {
            if ($record->check_in_at === null) {
                continue;
            }

            $key = $record->date->toDateString();
            $presentDates[$key] = true;
            $shift = $assignments->get($key)?->shift;

            $scheduledStart = DateHelper::atTime(
                $record->check_in_at,
                $shift?->start_time ?? $office->work_start,
            );

            $lateThreshold = $scheduledStart->copy()->addMinutes($office->late_tolerance_minutes);
            if ($record->check_in_at->gt($lateThreshold)) {
                $lateCount++;
                $lateMinutes += (int) $lateThreshold->diffInMinutes($record->check_in_at);
            }

            if ($record->check_out_at === null) {
                continue;
            }

            $scheduledEnd = DateHelper::atTime(
                $record->check_in_at,
                $shift?->end_time ?? $office->work_end,
            );
            if ($shift?->cross_day) {
                $scheduledEnd->addDay();
            }

            $extra = (int) $scheduledEnd->diffInMinutes($record->check_out_at, false);
            if (! $office->overtime_requires_approval && $extra >= $settings->overtime_min_minutes) {
                $overtimeMinutes += $extra;
            }
        }

        if ($office->overtime_requires_approval) {
            $overtimeMinutes = (int) OvertimeRequest::query()
                ->where('employee_id', $employee->id)
                ->where('status', OvertimeRequest::STATUS_APPROVED)
                ->whereBetween('date', [$periodStart->toDateString(), $periodEnd->toDateString()])
                ->sum('minutes');
        }

        $paidLeaveDates = [];
        $unpaidLeaveDates = [];

        foreach ($approvedLeaves as $leave) {
            $isPaid = $leave->leaveType?->is_paid ?? true;
            for ($day = $leave->start_date->copy(); $day->lte($leave->end_date); $day->addDay()) {
                if ($day->isWeekend() || isset($holidayLookup[$day->toDateString()]) || $day->year !== $year || $day->month !== $month) {
                    continue;
                }
                if ($isPaid) {
                    $paidLeaveDates[$day->toDateString()] = true;
                } else {
                    $unpaidLeaveDates[$day->toDateString()] = true;
                }
            }
        }

        $unpaidLeaveDays = count(array_diff_key($unpaidLeaveDates, $presentDates));
        $paidLeaveDays = count(array_diff_key($paidLeaveDates, $presentDates));

        $absentDays = 0;
        for ($day = $periodStart->copy(); $day->lte($absenceEnd); $day->addDay()) {
            if ($day->isWeekend() || isset($holidayLookup[$day->toDateString()])) {
                continue;
            }
            $key = $day->toDateString();
            if (isset($presentDates[$key]) || isset($paidLeaveDates[$key]) || isset($unpaidLeaveDates[$key])) {
                continue;
            }
            $absentDays++;
        }

        $presentCount = count($presentDates);
        $overtimePay = $settings->overtime_enabled && ($profile?->overtime_rate ?? 0) > 0
            ? (int) round($overtimeMinutes / 60 * $profile->overtime_rate)
            : 0;

        $earnings = [];
        $baseSalary = $profile?->base_salary ?? 0;
        if ($baseSalary > 0) {
            $earnings[] = ['label' => 'Gaji pokok', 'amount' => $baseSalary, 'detail' => null];
        }

        foreach ($allowances as $allowance) {
            /** @var SalaryAllowance $allowance */
            if ($allowance->amount <= 0) {
                continue;
            }
            $perPresentDay = $allowance->basis === 'per_present_day';
            $amount = $perPresentDay ? $allowance->amount * $presentCount : $allowance->amount;
            if ($amount <= 0) {
                continue;
            }
            $earnings[] = [
                'label' => $allowance->name,
                'amount' => $amount,
                'detail' => $perPresentDay
                    ? sprintf('%d hari × Rp %s', $presentCount, number_format($allowance->amount, 0, ',', '.'))
                    : null,
            ];
        }

        if ($overtimePay > 0 && $profile !== null) {
            $earnings[] = [
                'label' => 'Lembur',
                'amount' => $overtimePay,
                'detail' => sprintf(
                    '%d menit × Rp %s/jam',
                    $overtimeMinutes,
                    number_format($profile->overtime_rate, 0, ',', '.'),
                ),
            ];
        }

        $grossEarnings = array_sum(array_column($earnings, 'amount'));

        $deductions = [];
        foreach ($rules as $rule) {
            /** @var PayrollDeductionRule $rule */
            if (! $rule->is_active) {
                continue;
            }
            $result = $this->deductionFor(
                $rule,
                $lateCount,
                $lateMinutes,
                $absentDays,
                $unpaidLeaveDays,
                $dailyWage,
                $baseSalary,
                $grossEarnings,
                $profile?->ptkp_status ?? 'TK/0',
                $settings,
            );
            if ($result['amount'] <= 0) {
                continue;
            }
            $deductions[] = [
                'label' => $rule->name,
                'amount' => $result['amount'],
                'detail' => $result['detail'],
            ];
        }

        $totalDeductions = array_sum(array_column($deductions, 'amount'));
        $netPay = max(0, $grossEarnings - $totalDeductions);

        return [
            'employee_id' => $employee->id,
            'employee_name' => $employee->name,
            'nip' => (string) ($employee->nip ?? ''),
            'position' => (string) ($employee->position ?? ''),
            'year' => $year,
            'month' => $month,
            'base_salary' => $baseSalary,
            'earnings' => $earnings,
            'deductions' => $deductions,
            'gross_earnings' => $grossEarnings,
            'total_deductions' => $totalDeductions,
            'net_pay' => $netPay,
            'metrics' => [
                'workingDays' => $workingDays,
                'presentDays' => $presentCount,
                'lateCount' => $lateCount,
                'lateMinutes' => $lateMinutes,
                'absentDays' => $absentDays,
                'paidLeaveDays' => $paidLeaveDays,
                'unpaidLeaveDays' => $unpaidLeaveDays,
                'overtimeMinutes' => $overtimeMinutes,
            ],
        ];
    }

    public function monthlyPph21(
        int $grossEarnings,
        int $ptkp,
        array $brackets,
        int $grossMultiplier = 12,
    ): int {
        if ($grossEarnings <= 0) {
            return 0;
        }

        $annualGross = $grossEarnings * $grossMultiplier;
        $biayaJabatan = min((int) round($annualGross * 0.05), 6000000);
        $pkp = $annualGross - $ptkp - $biayaJabatan;
        if ($pkp <= 0) {
            return 0;
        }
        $pkp = intdiv($pkp, 1000) * 1000;

        $tax = 0.0;
        $lower = 0;
        $remaining = $pkp;
        foreach ($brackets as $bracket) {
            if ($remaining <= 0) {
                break;
            }
            $limit = $bracket['limit'] ?? null;
            $span = $limit === null ? $remaining : (int) $limit - $lower;
            if ($span <= 0) {
                if ($limit !== null) {
                    $lower = (int) $limit;
                }
                continue;
            }
            $taxable = min($remaining, $span);
            $tax += $taxable * ((float) $bracket['rate']) / 100;
            $remaining -= $taxable;
            if ($limit !== null) {
                $lower = (int) $limit;
            }
        }

        return (int) round($tax / 12);
    }

    /**
     * @return array{amount: int, detail: string|null}
     */
    private function deductionFor(
        PayrollDeductionRule $rule,
        int $lateCount,
        int $lateMinutes,
        int $absentDays,
        int $unpaidLeaveDays,
        int $dailyWage,
        int $baseSalary,
        int $grossEarnings,
        string $ptkpStatus,
        PayrollSetting $settings,
    ): array {
        $rate = (float) $rule->rate;

        return match ($rule->basis) {
            'late_per_occurrence' => $lateCount === 0
                ? ['amount' => 0, 'detail' => null]
                : ['amount' => (int) round($rate * $lateCount), 'detail' => sprintf('%d kejadian', $lateCount)],
            'late_per_minute' => $lateMinutes === 0
                ? ['amount' => 0, 'detail' => null]
                : ['amount' => (int) round($rate * $lateMinutes), 'detail' => sprintf('%d menit', $lateMinutes)],
            'absence_per_day' => $absentDays === 0
                ? ['amount' => 0, 'detail' => null]
                : ['amount' => (int) round($rate * $absentDays), 'detail' => sprintf('%d hari', $absentDays)],
            'absence_daily_wage' => ($absentDays === 0 || $dailyWage === 0)
                ? ['amount' => 0, 'detail' => null]
                : [
                    'amount' => (int) round($dailyWage * $rate / 100) * $absentDays,
                    'detail' => sprintf('%d hari × Rp %s', $absentDays, number_format((int) round($dailyWage * $rate / 100), 0, ',', '.')),
                ],
            'unpaid_leave_per_day' => $unpaidLeaveDays === 0
                ? ['amount' => 0, 'detail' => null]
                : ['amount' => (int) round($rate * $unpaidLeaveDays), 'detail' => sprintf('%d hari', $unpaidLeaveDays)],
            'unpaid_leave_daily_wage' => ($unpaidLeaveDays === 0 || $dailyWage === 0)
                ? ['amount' => 0, 'detail' => null]
                : [
                    'amount' => (int) round($dailyWage * $rate / 100) * $unpaidLeaveDays,
                    'detail' => sprintf('%d hari × Rp %s', $unpaidLeaveDays, number_format((int) round($dailyWage * $rate / 100), 0, ',', '.')),
                ],
            'percent_base' => $baseSalary === 0
                ? ['amount' => 0, 'detail' => null]
                : ['amount' => (int) round($baseSalary * $rate / 100), 'detail' => sprintf('%s%% dari gaji pokok', $this->rateText($rate))],
            'percent_gross' => $grossEarnings === 0
                ? ['amount' => 0, 'detail' => null]
                : ['amount' => (int) round($grossEarnings * $rate / 100), 'detail' => sprintf('%s%% dari pendapatan bruto', $this->rateText($rate))],
            'fixed_monthly' => ['amount' => (int) round($rate), 'detail' => 'Potongan tetap bulanan'],
            'pph21' => (function () use ($grossEarnings, $ptkpStatus, $settings): array {
                $amount = $this->monthlyPph21(
                    $grossEarnings,
                    $settings->ptkpFor($ptkpStatus),
                    $settings->pph21_brackets,
                );

                return $amount <= 0
                    ? ['amount' => 0, 'detail' => null]
                    : ['amount' => $amount, 'detail' => 'PTKP '.$ptkpStatus];
            })(),
            default => ['amount' => 0, 'detail' => null],
        };
    }

    private function rateText(float $rate): string
    {
        return $rate === (float) (int) $rate
            ? number_format($rate, 0, ',', '.')
            : rtrim(rtrim(number_format($rate, 1, ',', '.'), '0'), ',');
    }
}
