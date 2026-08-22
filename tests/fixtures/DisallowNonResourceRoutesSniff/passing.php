<?php

Route::resource('photos', PhotoController::class);
Route::apiResource('photos', ApiPhotoController::class);
Route::resources(['photos' => PhotoController::class]);
Route::apiResources(['photos' => ApiPhotoController::class]);
Route::fallback([FallbackController::class, '__invoke']);
Route::group(['prefix' => 'admin'], function (): void {
    Route::resource('invoices', InvoiceController::class);
});
Route::middleware('auth')->group(base_path('routes/admin.php'));
Route::prefix('admin')->group(base_path('routes/admin.php'));
Route::name('admin.')->group(base_path('routes/admin.php'));
Route::domain('admin.example.com')->group(base_path('routes/admin.php'));
Route::controller(PhotoController::class)->group(base_path('routes/photos.php'));

Route::getRoutes();
Route::postProcessor();
Route::matchedRoute();

Router::get('/photos', [PhotoController::class, 'index']);
ApiRoute::post('/photos', [PhotoController::class, 'store']);
$router::get('/photos', [PhotoController::class, 'index']);
$router->get('/photos', [PhotoController::class, 'index']);
static::get('/photos', [PhotoController::class, 'index']);

$defaultVerb = Route::GET;
$registrar = Route::get(...);
Route::middleware('auth')->get('/photos', [PhotoController::class, 'index']);
Route::prefix('admin')->post('/photos', [PhotoController::class, 'store']);
