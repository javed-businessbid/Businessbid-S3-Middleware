<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\S3FileToolController;

Route::get('/', function () {
    return response()->json([
        'status' => true,
        'message' => 'Businessbid Cloud Storage api is running.',
    ]);
});

Route::get('/clear-cache', function () {
    Artisan::call('cache:clear');
    Artisan::call('config:clear');
    Artisan::call('route:clear');
    Artisan::call('view:clear');
    Artisan::call('optimize:clear');
    return response()->json([
        'status' => true,
        'message' => 'Cache cleared successfully.',
    ]);

});

Route::get('/run-migration', function () {
    Artisan::call('migrate');
    $migrateOutput = Artisan::output();

    return response()->json([
        'status' => true,
        'message' => 'Migration run successfully.',
        'migrate_output' => $migrateOutput,
    ]);
});

Route::get('/s3-files', [S3FileToolController::class, 'index'])->name('s3-files.index');
Route::post('/s3-files/upload', [S3FileToolController::class, 'upload'])->name('s3-files.upload');
Route::post('/s3-files/temporary-url', [S3FileToolController::class, 'temporaryUrl'])->name('s3-files.temporary-url');