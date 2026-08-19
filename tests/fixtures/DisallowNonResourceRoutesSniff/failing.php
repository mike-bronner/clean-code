<?php

Route::get('/photos', [PhotoController::class, 'index']);
Route::post('/photos', [PhotoController::class, 'store']);
Route::put('/photos/{photo}', [PhotoController::class, 'update']);
Route::patch('/photos/{photo}', [PhotoController::class, 'patch']);
Route::delete('/photos/{photo}', [PhotoController::class, 'destroy']);
Route::options('/photos', [PhotoController::class, 'options']);
Route::any('/photos', [PhotoController::class, 'any']);
Route::match(['get', 'post'], '/photos', [PhotoController::class, 'index']);

Route::GET('/invoices', [InvoiceController::class, 'index']);
ROUTE::get('/receipts', [ReceiptController::class, 'index']);

\Route::post('/invoices', [InvoiceController::class, 'store']);
Illuminate\Support\Facades\Route::put('/invoices/{invoice}', [InvoiceController::class, 'update']);

Route::get(...$definition);

Route::group(['prefix' => 'admin'], function (): void {
    Route::get('/reports', [ReportController::class, 'index']);
});
