<?php

namespace App\Http\Middleware;

use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Response;

class RejectFailedFileUploads
{
    public function handle(Request $request, Closure $next): Response
    {
        $fields = [];

        foreach ($this->walkUploadedFiles($request->allFiles()) as $dotKey => $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }

            $error = $file->getError();
            if ($error === UPLOAD_ERR_OK || $error === UPLOAD_ERR_NO_FILE) {
                continue;
            }

            $field = $this->topLevelFieldKey($dotKey);
            $fields[$field][] = $this->messageForUploadError($error);
        }

        if ($fields === []) {
            return $next($request);
        }

        return ApiResponse::error(
            code: 'FILE_UPLOAD_FAILED',
            message: 'One or more file uploads failed.',
            fields: $fields,
            status: 422
        );
    }

    /**
     * @return iterable<string, UploadedFile>
     */
    private function walkUploadedFiles(array $files, string $prefix = ''): iterable
    {
        foreach ($files as $key => $file) {
            $path = $prefix === '' ? (string) $key : $prefix.'.'.$key;

            if ($file instanceof UploadedFile) {
                yield $path => $file;
            } elseif (is_array($file)) {
                yield from $this->walkUploadedFiles($file, $path);
            }
        }
    }

    private function topLevelFieldKey(string $dotPath): string
    {
        return explode('.', $dotPath, 2)[0];
    }

    private function messageForUploadError(int $error): string
    {
        $uploadMax = ini_get('upload_max_filesize');
        $postMax = ini_get('post_max_size');

        return match ($error) {
            UPLOAD_ERR_INI_SIZE => "The file exceeds PHP's upload_max_filesize limit (currently {$uploadMax}).",
            UPLOAD_ERR_FORM_SIZE => "The file exceeds the allowed size for this form (check MAX_FILE_SIZE or post_max_size, currently {$postMax}).",
            UPLOAD_ERR_PARTIAL => 'The file was only partially uploaded. Please try again.',
            UPLOAD_ERR_NO_TMP_DIR => 'Server is missing a temporary folder for uploads.',
            UPLOAD_ERR_CANT_WRITE => 'The file could not be written to disk on the server.',
            UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload.',
            default => 'The file upload failed.',
        };
    }
}
