<?php

use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\AttendanceCorrectionController;
use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\HolidayController;
use App\Http\Controllers\Api\LeaveBalanceController;
use App\Http\Controllers\Api\LeaveRequestController;
use App\Http\Controllers\Api\LeaveTypeController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\OfficeLocationController;
use App\Http\Controllers\Api\OfficeSettingController;
use App\Http\Controllers\Api\OvertimeRequestController;
use App\Http\Controllers\Api\PayrollDeductionRuleController;
use App\Http\Controllers\Api\PayrollSettingController;
use App\Http\Controllers\Api\PayrollSlipController;
use App\Http\Controllers\Api\PlatformTenantController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\SalaryAllowanceController;
use App\Http\Controllers\Api\SalaryProfileController;
use App\Http\Controllers\Api\ShiftAssignmentController;
use App\Http\Controllers\Api\UploadController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\WorkShiftController;
use App\Http\Middleware\LogApiActivity;
use Illuminate\Support\Facades\Route;

Route::post('auth/login', [AuthController::class, 'login']);

Route::middleware(['auth:sanctum', 'tenant', 'device', LogApiActivity::class])->group(function () {
    Route::post('auth/logout', [AuthController::class, 'logout']);
    Route::get('auth/me', [AuthController::class, 'me']);
    Route::put('auth/profile', [AuthController::class, 'updateProfile']);
    Route::put('auth/password', [AuthController::class, 'changePassword']);
    Route::post('auth/face', [AuthController::class, 'enrollFace']);
    Route::delete('auth/face', [AuthController::class, 'deleteFace']);
    Route::delete('auth/account', [AuthController::class, 'deleteAccount']);
    Route::get('time', [AuthController::class, 'serverTime']);

    Route::get('dashboard/summary', [DashboardController::class, 'summary']);

    Route::get('users', [UserController::class, 'index']);
    Route::post('users', [UserController::class, 'store']);
    Route::get('users/{user}', [UserController::class, 'show']);
    Route::put('users/{user}', [UserController::class, 'update']);
    Route::delete('users/{user}', [UserController::class, 'destroy']);

    Route::get('office-settings', [OfficeSettingController::class, 'show']);
    Route::put('office-settings', [OfficeSettingController::class, 'update']);

    Route::get('office-locations', [OfficeLocationController::class, 'index']);
    Route::post('office-locations', [OfficeLocationController::class, 'store']);
    Route::put('office-locations/{officeLocation}', [OfficeLocationController::class, 'update']);
    Route::delete('office-locations/{officeLocation}', [OfficeLocationController::class, 'destroy']);

    Route::get('holidays', [HolidayController::class, 'index']);
    Route::post('holidays', [HolidayController::class, 'store']);
    Route::put('holidays/{holiday}', [HolidayController::class, 'update']);
    Route::delete('holidays/{holiday}', [HolidayController::class, 'destroy']);

    Route::get('attendance/today', [AttendanceController::class, 'today']);
    Route::get('attendance/summary', [AttendanceController::class, 'summary']);
    Route::get('attendances', [AttendanceController::class, 'index']);
    Route::get('attendance', [AttendanceController::class, 'index']);
    Route::post('attendance/check-in', [AttendanceController::class, 'checkIn']);
    Route::post('attendance/check-out', [AttendanceController::class, 'checkOut']);

    Route::get('shifts', [WorkShiftController::class, 'index']);
    Route::post('shifts', [WorkShiftController::class, 'store']);
    Route::put('shifts/{shift}', [WorkShiftController::class, 'update']);
    Route::delete('shifts/{shift}', [WorkShiftController::class, 'destroy']);

    Route::get('shift-assignments', [ShiftAssignmentController::class, 'index']);
    Route::post('shift-assignments', [ShiftAssignmentController::class, 'store']);
    Route::delete('shift-assignments/{assignment}', [ShiftAssignmentController::class, 'destroy']);

    Route::get('leave-types', [LeaveTypeController::class, 'index']);

    Route::get('leave-balances', [LeaveBalanceController::class, 'index']);
    Route::put('leave-balances', [LeaveBalanceController::class, 'update']);

    Route::get('leave-requests', [LeaveRequestController::class, 'index']);
    Route::post('leave-requests', [LeaveRequestController::class, 'store']);
    Route::get('leave-requests/{leaveRequest}', [LeaveRequestController::class, 'show']);
    Route::patch('leave-requests/{leaveRequest}/cancel', [LeaveRequestController::class, 'cancel']);
    Route::patch('leave-requests/{leaveRequest}/review', [LeaveRequestController::class, 'review']);

    Route::get('attendance-corrections', [AttendanceCorrectionController::class, 'index']);
    Route::post('attendance-corrections', [AttendanceCorrectionController::class, 'store']);
    Route::get('attendance-corrections/{attendanceCorrection}', [AttendanceCorrectionController::class, 'show']);
    Route::patch('attendance-corrections/{attendanceCorrection}/cancel', [AttendanceCorrectionController::class, 'cancel']);
    Route::patch('attendance-corrections/{attendanceCorrection}/review', [AttendanceCorrectionController::class, 'review']);

    Route::get('overtime-requests', [OvertimeRequestController::class, 'index']);
    Route::post('overtime-requests', [OvertimeRequestController::class, 'store']);
    Route::get('overtime-requests/{overtimeRequest}', [OvertimeRequestController::class, 'show']);
    Route::patch('overtime-requests/{overtimeRequest}/cancel', [OvertimeRequestController::class, 'cancel']);
    Route::patch('overtime-requests/{overtimeRequest}/review', [OvertimeRequestController::class, 'review']);

    Route::get('notifications', [NotificationController::class, 'index']);
    Route::patch('notifications/read-all', [NotificationController::class, 'markAllRead']);
    Route::patch('notifications/{notification}/read', [NotificationController::class, 'markRead']);

    Route::get('salary-profiles', [SalaryProfileController::class, 'index']);
    Route::post('salary-profiles', [SalaryProfileController::class, 'upsert']);
    Route::put('salary-profiles', [SalaryProfileController::class, 'upsert']);
    Route::get('salary-profiles/{salaryProfile}', [SalaryProfileController::class, 'show']);
    Route::delete('salary-profiles/{salaryProfile}', [SalaryProfileController::class, 'destroy']);

    Route::get('salary-allowances', [SalaryAllowanceController::class, 'index']);
    Route::put('salary-allowances', [SalaryAllowanceController::class, 'replace']);
    Route::post('salary-allowances', [SalaryAllowanceController::class, 'replace']);

    Route::get('payroll-deduction-rules', [PayrollDeductionRuleController::class, 'index']);
    Route::post('payroll-deduction-rules', [PayrollDeductionRuleController::class, 'store']);
    Route::put('payroll-deduction-rules/{payrollDeductionRule}', [PayrollDeductionRuleController::class, 'update']);
    Route::delete('payroll-deduction-rules/{payrollDeductionRule}', [PayrollDeductionRuleController::class, 'destroy']);

    Route::get('payroll-settings', [PayrollSettingController::class, 'show']);
    Route::put('payroll-settings', [PayrollSettingController::class, 'update']);

    Route::get('payroll-slips', [PayrollSlipController::class, 'index']);
    Route::post('payroll-slips/finalize', [PayrollSlipController::class, 'finalize']);
    Route::delete('payroll-slips', [PayrollSlipController::class, 'destroy']);
    Route::get('payroll-slips/{payrollSlip}', [PayrollSlipController::class, 'show']);

    Route::get('reports/attendance', [ReportController::class, 'attendance']);
    Route::get('reports/payroll', [ReportController::class, 'payroll']);

    Route::get('audit-logs', [AuditLogController::class, 'index']);

    Route::post('uploads', [UploadController::class, 'store']);
});

// Platform hanya untuk super admin. Tidak ada pendaftaran publik.
Route::middleware(['auth:sanctum', 'role:super_admin', LogApiActivity::class])
    ->prefix('platform')
    ->group(function () {
        Route::get('tenants', [PlatformTenantController::class, 'index']);
        Route::post('tenants', [PlatformTenantController::class, 'store']);
        Route::get('tenants/{tenant}', [PlatformTenantController::class, 'show']);
        Route::patch('tenants/{tenant}', [PlatformTenantController::class, 'update']);
    });
