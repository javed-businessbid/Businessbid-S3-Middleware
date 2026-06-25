<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\S3FileToolController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/clear-cache', function () {
    Artisan::call('cache:clear');
    Artisan::call('config:clear');
    Artisan::call('route:clear');
    Artisan::call('view:clear');
    Artisan::call('optimize:clear');
    return 'Cache cleared successfully';

});

Route::get('/run-migration', function () {
    Artisan::call('migrate');
    $migrateOutput = Artisan::output();

    return response()->json([
        'message' => 'Migration run successfully.',
        'migrate_output' => $migrateOutput,
    ]);
});

// Route::get('/run-seed', function () {
//     Artisan::call('db:seed --class=RolesAndPermissionsSeeder');
//     $seedOutput = Artisan::output();
//     return response()->json([
//         'message' => 'Seed run successfully.',
//         'seed_output' => $seedOutput,
//     ]);
// });

Route::get('/storage-link', function () {
    Artisan::call('storage:link');
    $storageLinkOutput = Artisan::output();
    return response()->json([
        'message' => 'Storage link created successfully.',
        'storage_link_output' => $storageLinkOutput,
    ]);
});

Route::get('/s3-files', [S3FileToolController::class, 'index'])->name('s3-files.index');
Route::post('/s3-files/upload', [S3FileToolController::class, 'upload'])->name('s3-files.upload');
Route::post('/s3-files/temporary-url', [S3FileToolController::class, 'temporaryUrl'])->name('s3-files.temporary-url');