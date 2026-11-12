<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\AuthorizesRoles;
use App\Http\Controllers\Controller;
use App\Http\Resources\LeaveBalanceResource;
use App\Models\LeaveBalance;
use App\Support\LeaveBalances;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;

class LeaveBalanceController extends Controller
{
    use AuthorizesRoles;

    public function index(Request $request): AnonymousResourceCollection
    {
        $actor = $request->user();
        $employeeId = $request->filled('employee_id')
            ? (int) $request->query('employee_id')
            : $actor->id;

        $this->ensureSelfOrManager($request, $employeeId);

        $year = (int) $request->query('year', Carbon::now()->year);
        LeaveBalances::ensure($employeeId, $year);

        return LeaveBalanceResource::collection(
            LeaveBalance::query()
                ->with('leaveType')
                ->where('employee_id', $employeeId)
                ->where('year', $year)
                ->orderBy('leave_type_id')
                ->get(),
        );
    }

    public function update(Request $request): JsonResponse
    {
        $this->ensureManager($request);

        $data = $request->validate([
            'employee_id' => ['required', 'integer', 'exists:users,id'],
            'leave_type_id' => ['required', 'integer', 'exists:leave_types,id'],
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'entitled_days' => ['sometimes', 'integer', 'min:0'],
            'used_days' => ['sometimes', 'integer', 'min:0'],
            'adjustment_days' => ['sometimes', 'integer'],
        ]);

        $balance = LeaveBalance::query()->updateOrCreate(
            [
                'employee_id' => $data['employee_id'],
                'leave_type_id' => $data['leave_type_id'],
                'year' => $data['year'],
            ],
            [
                'entitled_days' => $data['entitled_days'] ?? 0,
                'used_days' => $data['used_days'] ?? 0,
                'adjustment_days' => $data['adjustment_days'] ?? 0,
            ],
        );

        return (new LeaveBalanceResource($balance->load('leaveType')))
            ->response()
            ->setStatusCode(200);
    }
}
