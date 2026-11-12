<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\AuthorizesRoles;
use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\PayrollSlip;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    use AuthorizesRoles;

    public function attendance(Request $request): StreamedResponse
    {
        $this->ensureManager($request);

        $from = $request->query('from', now()->startOfMonth()->toDateString());
        $to = $request->query('to', now()->toDateString());
        $employeeId = $request->filled('employee_id') ? (int) $request->query('employee_id') : null;

        $query = Attendance::query()
            ->with('user')
            ->whereBetween('date', [$from, $to])
            ->when($employeeId !== null, fn ($builder) => $builder->where('user_id', $employeeId))
            ->orderBy('date')
            ->orderBy('user_id');

        $filename = sprintf('absensi-%s-sd-%s.csv', $from, $to);

        return response()->streamDownload(function () use ($query) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'Tanggal', 'NIP', 'Nama', 'Tim', 'Masuk', 'Pulang',
                'Status', 'Jarak Masuk (m)', 'Jarak Pulang (m)',
                'Mock GPS', 'Liveness', 'Face Score',
            ]);

            foreach ($query->cursor() as $record) {
                fputcsv($handle, [
                    $record->date?->toDateString(),
                    $record->user?->nip,
                    $record->user?->name,
                    $record->user?->team,
                    $record->check_in_at?->format('H:i'),
                    $record->check_out_at?->format('H:i'),
                    $record->status,
                    $record->check_in_distance,
                    $record->check_out_distance,
                    $record->is_mocked ? 'Ya' : 'Tidak',
                    $record->liveness_passed ? 'Lolos' : 'Gagal',
                    $record->face_match_score,
                ]);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function payroll(Request $request): StreamedResponse
    {
        $this->ensureManager($request);

        $year = (int) $request->query('year', now()->year);
        $month = (int) $request->query('month', now()->month);

        $query = PayrollSlip::query()
            ->where('period_year', $year)
            ->where('period_month', $month)
            ->orderBy('employee_name');

        $filename = sprintf('gaji-%04d-%02d.csv', $year, $month);

        return response()->streamDownload(function () use ($query) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'NIP', 'Nama', 'Jabatan', 'Gaji Pokok', 'Pendapatan Bruto',
                'Total Potongan', 'Gaji Bersih', 'Status',
            ]);

            foreach ($query->cursor() as $slip) {
                fputcsv($handle, [
                    $slip->nip,
                    $slip->employee_name,
                    $slip->position,
                    $slip->base_salary,
                    $slip->gross_earnings,
                    $slip->total_deductions,
                    $slip->net_pay,
                    $slip->status,
                ]);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
