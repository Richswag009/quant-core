<?php

use App\Http\Controllers\AuditTrailController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BatchController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::get('/health', function () {
    return response()->json([
        'status' => 'OK',
        'timestamp' => now()->toISOString(),
        'app' => 'quannt-core API',
        'version' => '1.0.0',
    ]);
});

Route::prefix('v1')->group(function () {

    Route::prefix('auth')->group(function () {
        Route::post('login', [AuthController::class, 'login']);

        Route::middleware(['auth:sanctum'])->group(function () {

            Route::get('/user', [AuthController::class, 'user']);

            Route::post('/logout', [AuthController::class, 'logout']);
        });
    });

    Route::middleware(['auth:sanctum'])->group(function () {
        Route::prefix('batches')->group(function () {

            Route::post('/', [BatchController::class, 'createBatch']);
            Route::get('/', [BatchController::class, 'getAllBatches']);
            Route::get('{batch}', [BatchController::class, 'getBatch']);
            Route::delete('{batch}', [BatchController::class, 'deleteBatch']);
            Route::get('{batch}/items', [BatchController::class, 'getBatchItems']);
            Route::post('{batch}/validate', [BatchController::class, 'validateBatch']);
            Route::post('{batch}/submit', [BatchController::class, 'submitBatch']);
            Route::post('{batch}/approve', [BatchController::class, 'approveBatch']);
            Route::post('{batch}/reject', [BatchController::class, 'rejectBatch']);
            Route::post('{batch}/post', [BatchController::class, 'postBatch']);
            Route::post('{batch}/retry', [BatchController::class, 'retryBatch']);
            Route::get('{batch}/audits', [AuditTrailController::class, 'index']);
        });
    });
});

Route::fallback(function () {
    return response()->json([
        'status' => false,
        'message' => 'Route not found. Please check the URL or method.',
    ], 404);
});
