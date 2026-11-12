<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\AuthorizesRoles;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdatePayrollSettingsRequest;
use App\Http\Resources\PayrollSettingResource;
use App\Models\PayrollSetting;
use Illuminate\Http\Request;

class PayrollSettingController extends Controller
{
    use AuthorizesRoles;

    public function show(Request $request): PayrollSettingResource
    {
        return new PayrollSettingResource(PayrollSetting::current());
    }

    public function update(UpdatePayrollSettingsRequest $request): PayrollSettingResource
    {
        $this->ensureAdmin($request);

        $settings = PayrollSetting::current();
        $settings->fill($request->validated())->save();

        return new PayrollSettingResource($settings->refresh());
    }
}
