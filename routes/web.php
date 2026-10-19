<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\SchoolClassController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\AbsenceNoteController;
use App\Http\Controllers\ManualAttendanceController;
use App\Http\Controllers\ScanController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('login'));

// Auth
Route::get('/login', [AuthController::class, 'showLogin'])
    ->middleware('guest')
    ->name('login');

Route::post('/login', [AuthController::class, 'login'])
    ->middleware(['guest', 'throttle:5,1'])
    ->name('login.attempt');

Route::post('/logout', [AuthController::class, 'logout'])
    ->middleware('auth')
    ->name('logout');

// Admin (butuh login)
Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard/data', [DashboardController::class, 'data'])->name('dashboard.data');
    Route::resource('classes', SchoolClassController::class)->except(['show']);
    Route::resource('students', StudentController::class)->except(['show']);
    Route::resource('absence-notes', AbsenceNoteController::class)->except(['show']);
    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('/reports/pdf', [ReportController::class, 'pdf'])->name('reports.pdf');
    Route::get('/reports/excel', [ReportController::class, 'excel'])->name('reports.excel');
    Route::get('/absen-manual', [ManualAttendanceController::class, 'index'])->name('manual-attendance.index');
    Route::post('/absen-manual', [ManualAttendanceController::class, 'store'])->name('manual-attendance.store');
});

Route::get('/scan', [ScanController::class, 'index'])->name('scan');

Route::post('/scan', [ScanController::class, 'store'])
    ->middleware('throttle:60,1')
    ->name('scan.store');