<?php

use App\Http\Controllers\PackageController;
use App\Http\Controllers\TransactionController;
use Illuminate\Support\Facades\Route;

// Resource untuk paket
Route::apiResource('packages', PackageController::class);

// Route refund transaksi
Route::put('/transactions/refund/{id}', [TransactionController::class, 'refund']);

Route::prefix('v1')->group(function () {

    Route::get('/slots/today', [TransactionController::class, 'getTodaySlot']);

    Route::post('/tickets/validate', [TransactionController::class, 'validateTicket']);

    Route::post('/transactions/create', [TransactionController::class, 'store']);

});