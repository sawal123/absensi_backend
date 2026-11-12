<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\AuthorizesRoles;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDeductionRuleRequest;
use App\Http\Resources\PayrollDeductionRuleResource;
use App\Models\PayrollDeductionRule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;

class PayrollDeductionRuleController extends Controller
{
    use AuthorizesRoles;

    public function index(Request $request): AnonymousResourceCollection
    {
        return PayrollDeductionRuleResource::collection(
            PayrollDeductionRule::query()->orderBy('sort_order')->orderBy('id')->get(),
        );
    }

    public function store(StoreDeductionRuleRequest $request): JsonResponse
    {
        $this->ensureManager($request);

        $data = $request->validated();
        $data['is_active'] = $data['is_active'] ?? true;
        $data['sort_order'] = $data['sort_order'] ?? (PayrollDeductionRule::query()->max('sort_order') + 1);

        $rule = PayrollDeductionRule::query()->create($data);

        return (new PayrollDeductionRuleResource($rule))
            ->response()
            ->setStatusCode(201);
    }

    public function update(StoreDeductionRuleRequest $request, PayrollDeductionRule $payrollDeductionRule): PayrollDeductionRuleResource
    {
        $this->ensureManager($request);

        $payrollDeductionRule->fill($request->validated())->save();

        return new PayrollDeductionRuleResource($payrollDeductionRule->refresh());
    }

    public function destroy(Request $request, PayrollDeductionRule $payrollDeductionRule): JsonResponse
    {
        $this->ensureManager($request);

        if ($payrollDeductionRule->is_system) {
            throw ValidationException::withMessages([
                'id' => 'Komponen bawaan tidak bisa dihapus.',
            ]);
        }

        $payrollDeductionRule->delete();

        return response()->json(['data' => ['message' => 'Komponen potongan dihapus.']]);
    }
}
