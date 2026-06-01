<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PackageController;
use App\Http\Controllers\TransactionController;

/*
|--------------------------------------------------------------------------
| Authentication Routes (Guest)
|--------------------------------------------------------------------------
*/
Route::get('/', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('auth.login');
Route::post('/logout', [AuthController::class, 'logout'])->name('auth.logout');

/*
|--------------------------------------------------------------------------
| Admin Protected Routes
|--------------------------------------------------------------------------
*/
Route::middleware('auth.admin')
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {

        // Dashboard
        Route::get('/dashboard', [DashboardController::class, 'index'])
            ->name('dashboard');
        Route::get('/dashboard-summary', [DashboardController::class, 'summary'])
            ->name('dashboard.summary');
        Route::post('/settings/capacity', [DashboardController::class, 'updateCapacity'])
            ->name('settings.capacity');

        // Packages
        Route::controller(PackageController::class)->group(function () {
            Route::get('/packages', 'index')->name('packages.index');
            Route::post('/packages', 'store')->name('packages.store');
            Route::put('/packages/{id}', 'update')->name('packages.update');
            Route::delete('/packages/{id}', 'destroy')->name('packages.destroy');
        });

        // Transactions
        Route::controller(TransactionController::class)->group(function () {
            Route::get('/transactions', 'index')->name('transactions.index');
            Route::get('/transactions/{id}', 'show')->name('transactions.show');
            Route::put('/transactions/refund/{id}', 'refund')
                ->name('transactions.refund');
        });

        // Laporan
        Route::get('/export/pdf', [DashboardController::class, 'exportPdf'])->name('export.pdf');
        Route::get('/export/excel', [DashboardController::class, 'exportExcel'])->name('export.excel');
    });