<?php

use App\Http\Controllers\Api\V1\CustomerController;
use App\Http\Controllers\Api\V1\ExpenseController;
use App\Http\Controllers\Api\V1\InvoiceController;
use App\Http\Controllers\Api\V1\PaymentController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\QuoteController;
use App\Http\Controllers\Api\V1\TransactionController;
use App\Http\Controllers\Api\V1\VendorController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware('api.auth')->group(function () {
    Route::get('/me', function (\Illuminate\Http\Request $request) {
        $app = $request->attributes->get('apiApplication');
        return response()->json([
            'company_id' => $app->company_id,
            'application' => $app->name,
        ]);
    });

    Route::apiResource('customers', CustomerController::class);
    Route::apiResource('products', ProductController::class);
    Route::apiResource('quotes', QuoteController::class)->only(['index','store','show','update','destroy']);
    Route::apiResource('invoices', InvoiceController::class);
    Route::apiResource('payments', PaymentController::class)->only(['index','store','show']);
    Route::post('/payments/{payment}/void', [PaymentController::class, 'void']);
    Route::apiResource('expenses', ExpenseController::class)->only(['index','store','show','update']);
    Route::post('/expenses/{expense}/void', [ExpenseController::class, 'void']);
    Route::apiResource('vendors', VendorController::class)->only(['index','store','show','update','destroy']);
    Route::apiResource('transactions', TransactionController::class)->only(['index','show']);
    Route::post('/transactions/transfer', [TransactionController::class, 'transfer']);
    Route::post('/transactions/income', [TransactionController::class, 'income']);
    Route::post('/transactions/adjustment', [TransactionController::class, 'adjustment']);
});
