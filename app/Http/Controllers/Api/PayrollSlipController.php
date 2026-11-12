<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\AuthorizesRoles;
use App\Http\Controllers\Controller;
use App\Http\Resources\PayrollSlipResource;
use App\Models\PayrollSlip;
use App\Models\User;
use App\Services\PayrollCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class PayrollSlipController extends Controller
{
    use AuthorizesRoles;

    public function index(Request $request): AnonymousResourceCollection
    {
        $actor = $request->user();
        $period = Carbon::now()->subMonthNoOverflow();

        $year = (int) $request->query('year', $period->year);
        $month = (int) $request->query('month', $period->month);

        $query = PayrollSlip::query()
            ->where('period_year', $year)
            ->where('period_month', $month)
            ->orderBy('employee_name');

        if ($actor->isManager()) {
            if ($request->filled('employee_id')) {
                $query->where('employee_id', (int) $request->query('employee_id'));
            }
        } else {
            $query->where('employee_id', $actor->id);
        }

        return PayrollSlipResource::collection($query->get());
    }

    public function show(Request $request, PayrollSlip $payrollSlip): PayrollSlipResource
    {
        $this->ensureSelfOrManager($request, $payrollSlip->employee_id);

        return new PayrollSlipResource($payrollSlip);
    }

    public function finalize(Request $request, PayrollCalculator $calculator): JsonResponse
    {
        $actor = $this->ensureManager($request);

        $data = $request->validate([
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
            'employee_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $year = (int) $data['year'];
        $month = (int) $data['month'];

        $employees = User::query()
            ->active()
            ->when(
                ! empty($data['employee_id']),
                fn ($query) => $query->whereKey((int) $data['employee_id']),
                fn ($query) => $query->where('role', User::ROLE_EMPLOYEE),
            )
            ->get();

        $slips = DB::transaction(function () use ($employees, $calculator, $year, $month, $actor) {
            $result = [];

            foreach ($employees as $employee) {
                $computed = $calculator->compute($employee, $year, $month);

                $slip = PayrollSlip::query()->updateOrCreate(
                    [
                        'employee_id' => $employee->id,
                        'period_year' => $year,
                        'period_month' => $month,
                    ],
                    [
                        'employee_name' => $computed['employee_name'],
                        'nip' => $computed['nip'],
                        'position' => $computed['position'],
                        'base_salary' => $computed['base_salary'],
                        'gross_earnings' => $computed['gross_earnings'],
                        'total_deductions' => $computed['total_deductions'],
                        'net_pay' => $computed['net_pay'],
                        'breakdown' => [
                            'earnings' => $computed['earnings'],
                            'deductions' => $computed['deductions'],
                            'metrics' => $computed['metrics'],
                        ],
                        'status' => 'final',
                        'finalized_by' => $actor->id,
                        'finalized_at' => Carbon::now(),
                    ],
                );

                $result[] = $slip;
            }

            return $result;
        });

        return response()->json([
            'data' => PayrollSlipResource::collection(collect($slips))->resolve($request),
        ]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $this->ensureAdmin($request);

        $request->merge([
            'year' => $request->input('year', $request->query('year')),
            'month' => $request->input('month', $request->query('month')),
        ]);

        $data = $request->validate([
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
        ]);

        $deleted = PayrollSlip::query()
            ->where('period_year', (int) $data['year'])
            ->where('period_month', (int) $data['month'])
            ->delete();

        return response()->json([
            'data' => [
                'message' => 'Finalisasi gaji dibuka kembali.',
                'deleted' => $deleted,
            ],
        ]);
    }
}
