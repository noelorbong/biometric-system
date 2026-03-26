<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Models\User;

use App\Http\Controllers\AcademicYearController;

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\CampusController;
use App\Http\Controllers\CollegeController;
use App\Http\Controllers\ProgramController;
use App\Http\Controllers\MediaFileController;
use App\Http\Controllers\OfficeShiftController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\BiometricReportController;
use App\Http\Controllers\AppSettingController;
use App\Http\Controllers\MachineController;
use App\Http\Controllers\LicenseController;


// License routes — public so they can gate the app before login
Route::post('/license/status', [LicenseController::class, 'status']);
Route::post('/license/activate', [LicenseController::class, 'activate']);
Route::post('/license/deactivate', [LicenseController::class, 'deactivate'])->middleware('auth:sanctum');

#region ImageController
Route::post('/media/upload', [MediaFileController::class, 'upload']);
#endregion

Route::post('/dashboard/data', [DashboardController::class, 'index'])->middleware('auth:sanctum');


Route::post('/user', [UserController::class, 'user'])->middleware('auth:sanctum');
Route::post('/users', [UserController::class, 'index'])->middleware('auth:sanctum');
Route::post('/user/checkinout', [UserController::class, 'checkinout'])->middleware('auth:sanctum');
Route::post('/user/checkinout/override/store', [UserController::class, 'storeCheckinoutOverride'])->middleware('auth:sanctum');
Route::post('/user/checkinout/override/update', [UserController::class, 'updateCheckinoutOverride'])->middleware('auth:sanctum');
Route::post('/user/checkinout/override/delete', [UserController::class, 'deleteCheckinoutOverride'])->middleware('auth:sanctum');
Route::post('/report/biometric', [BiometricReportController::class, 'generate'])->middleware('auth:sanctum');
Route::post('/user/office-shift/update', [UserController::class, 'updateOfficeShift'])->middleware('auth:sanctum');
Route::post('/user/affiliation/update', [UserController::class, 'updateAffiliation'])->middleware('auth:sanctum');
Route::post('/office-shifts', [OfficeShiftController::class, 'index'])->middleware('auth:sanctum');
Route::post('/office-shift/store', [OfficeShiftController::class, 'store'])->middleware('auth:sanctum');
Route::post('/office-shift/update', [OfficeShiftController::class, 'update'])->middleware('auth:sanctum');
Route::post('/office-shift/delete', [OfficeShiftController::class, 'delete'])->middleware('auth:sanctum');
Route::post('/departments', [DepartmentController::class, 'index'])->middleware('auth:sanctum');
Route::post('/department/store', [DepartmentController::class, 'store'])->middleware('auth:sanctum');
Route::post('/department/update', [DepartmentController::class, 'update'])->middleware('auth:sanctum');
Route::post('/department/delete', [DepartmentController::class, 'delete'])->middleware('auth:sanctum');
Route::post('/colleges', [CollegeController::class, 'index'])->middleware('auth:sanctum');
Route::post('/college/store', [CollegeController::class, 'store'])->middleware('auth:sanctum');
Route::post('/college/update', [CollegeController::class, 'update'])->middleware('auth:sanctum');
Route::post('/college/delete', [CollegeController::class, 'delete'])->middleware('auth:sanctum');
Route::post('/machines', [MachineController::class, 'index'])->middleware('auth:sanctum');
Route::post('/machine/store', [MachineController::class, 'store'])->middleware('auth:sanctum');
Route::post('/machine/update', [MachineController::class, 'update'])->middleware('auth:sanctum');
Route::post('/machine/delete', [MachineController::class, 'delete'])->middleware('auth:sanctum');
Route::post('/machine/connect', [MachineController::class, 'testConnection'])->middleware('auth:sanctum');
Route::post('/machine/auto-sync-status', [MachineController::class, 'autoSyncStatus'])->middleware('auth:sanctum');
Route::post('/machine/sync-attendance', [MachineController::class, 'syncAttendance'])->middleware('auth:sanctum');
Route::post('/machine/download-users', [MachineController::class, 'downloadUsers'])->middleware('auth:sanctum');
Route::post('/machine/download-users-progress', [MachineController::class, 'downloadUsersProgress'])->middleware('auth:sanctum');
Route::post('/machine/clear-attendance', [MachineController::class, 'clearAttendanceLogs'])->middleware('auth:sanctum');
Route::post('/machine/sync-user-templates', [MachineController::class, 'syncUserTemplates'])->middleware('auth:sanctum');
Route::post('/machine/push-users', [MachineController::class, 'pushUsersToMachine'])->middleware('auth:sanctum');
Route::post('/machine/push-user', [MachineController::class, 'pushSingleUserToMachine'])->middleware('auth:sanctum');
Route::post('/machine/enroll-fingerprint', [MachineController::class, 'enrollFingerprint'])->middleware('auth:sanctum');
Route::post('/machine/enroll-face', [MachineController::class, 'enrollFace'])->middleware('auth:sanctum');
Route::post('/machine/enrollment-face-status', [MachineController::class, 'enrollmentFaceStatus'])->middleware('auth:sanctum');
Route::post('/machine/enrollment-template-status', [MachineController::class, 'enrollmentTemplateStatus'])->middleware('auth:sanctum');
Route::post('/settings', [AppSettingController::class, 'index'])->middleware('auth:sanctum');
Route::post('/settings/update', [AppSettingController::class, 'update'])->middleware('auth:sanctum');
Route::post('/settings/maintenance-patch', [AppSettingController::class, 'runMaintenancePatch'])->middleware('auth:sanctum');
Route::post('/settings/system-update', [AppSettingController::class, 'runSystemUpdate'])->middleware('auth:sanctum');
Route::post('/user/update', [UserController::class, 'update'])->middleware('auth:sanctum');
Route::post('/user/store', [UserController::class, 'store'])->middleware('auth:sanctum');
Route::post('/user/delete', [UserController::class, 'delete'])->middleware('auth:sanctum');
