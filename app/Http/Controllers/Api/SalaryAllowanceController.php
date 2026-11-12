<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\AuthorizesRoles;
use App\Http\Controllers\Controller;
use App\Http\Requests\ReplaceSalaryAllowancesRequest;
use App\Http\Resources\SalaryAllowanceResource;
use App\Models\SalaryAllowance;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class SalaryAllowanceController extends Controller
{
    use AuthorizesRoles;

    public function index(Request $request): AnonymousResourceCollection
    {
        $actor = $request->user();

        $query = SalaryAllowance::query();

        if ($actor->isManager()) {
            if ($request->filled('employee_id')) {
                $query->where('employee_id', (int) $request->query('employee_id'));
            }
        } else {
            $query->where('employee_id', $actor->id);
        }

        return SalaryAllowanceResource::collection(
            $query->orderBy('employee_id')->orderBy('sort_order')->get(),
        );
    }

    public function replace(ReplaceSalaryAllowancesRequest $request): AnonymousResourceCollection
    {
        $this->ensureManager($request);
        $data = $request->validated();
        $employeeId = (int) $data['employee_id'];

        DB::transaction(function () use ($data, $employeeId) {
            SalaryAllowance::query()->where('employee_id', $employeeId)->delete();

            foreach (array_values($data['allowances']) as $index => $item) {
                SalaryAllowance::query()->create([
                    'employee_id' => $employeeId,
                    'name' => $item['name'],
                    'amount' => (int) $item['amount'],
                    'basis' => $item['basis'],
                    'sort_order' => (int) ($item['sort_order'] ?? $index),
                ]);
            }
        });

        return SalaryAllowanceResource::collection(
            SalaryAllowance::query()
                ->where('employee_id', $employeeId)
                ->orderBy('sort_order')
                ->get(),
        );
    }
}
