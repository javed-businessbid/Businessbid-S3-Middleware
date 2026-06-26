<?php

use App\Http\Controllers\Api\FileController;
use Illuminate\Support\Facades\Route;

Route::get('s3-files/upload-attachment', function () {
    return response()->json([
        'error' => [
            'code' => 'METHOD_NOT_ALLOWED',
            'message' => 'This endpoint only accepts POST requests.',
        ],
    ], 405);
});

Route::post('s3-files/upload-attachment', [FileController::class, 'uploadAttachment']);
Route::post('s3-files/temporary-public-url', [FileController::class, 'temporaryPublicUrl']);
