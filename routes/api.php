<?php

use App\Http\Controllers\Api\FileController;
use Illuminate\Support\Facades\Route;

Route::post('s3-files/upload-attachment', [FileController::class, 'uploadAttachment']);
Route::post('s3-files/temporary-public-url', [FileController::class, 'temporaryPublicUrl']);