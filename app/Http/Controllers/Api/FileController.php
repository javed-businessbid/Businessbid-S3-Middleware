<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\File;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class FileController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $workspaceId = (int) $request->attributes->get('workspace_id', 0);

        $files = File::query()
            ->where('workspace_id', $workspaceId)
            ->latest()
            ->paginate((int) $request->integer('per_page', 15));

        return response()->json($files);
    }

    public function upload(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'file' => ['required', 'file'],
            'client_id' => ['nullable', 'integer'],
            'category' => ['nullable', 'string', 'max:64'],
            'type' => ['nullable', 'string', 'max:32'],
            'access_level' => ['nullable', 'string', 'max:32'],
        ]);

        $uploadedFile = $request->file('file');
        if (! $uploadedFile) {
            throw ValidationException::withMessages([
                'file' => ['The file field is required.'],
            ]);
        }

        $workspaceId = (int) $request->attributes->get('workspace_id', 0);
        $disk = 's3';
        $directory = 'documents/'.now()->format('Y/m');
        $storedName = Str::uuid()->toString().'.'.$uploadedFile->getClientOriginalExtension();
        $storagePath = trim($directory.'/'.$storedName, '/');

        Storage::disk($disk)->putFileAs(
            $directory,
            $uploadedFile,
            $storedName,
            ['visibility' => 'private']
        );

        $file = File::create([
            'workspace_id' => $workspaceId,
            'client_id' => $validated['client_id'] ?? null,
            'owner_id' => $request->user()?->id,
            'file_name' => $uploadedFile->getClientOriginalName(),
            'storage_disk' => $disk,
            'storage_path' => $storagePath,
            'mime_type' => $uploadedFile->getClientMimeType(),
            'type' => $validated['type'] ?? ($uploadedFile->getClientOriginalExtension() ?: null),
            'size' => $uploadedFile->getSize() ?: 0,
            'category' => $validated['category'] ?? null,
            'access_level' => $validated['access_level'] ?? 'Internal',
            'version' => 1,
        ]);

        return response()->json([
            'message' => 'File uploaded successfully.',
            'data' => $file,
        ], Response::HTTP_CREATED);
    }

    public function download(Request $request, File $file): JsonResponse
    {
        $workspaceId = (int) $request->attributes->get('workspace_id', 0);
        if ((int) $file->workspace_id !== $workspaceId) {
            abort(Response::HTTP_NOT_FOUND);
        }

        $disk = Storage::disk($file->storage_disk);
        $expiresAt = now()->addMinutes((int) $request->integer('expires_in', 15));

        try {
            $temporaryUrl = $disk->temporaryUrl($file->storage_path, $expiresAt);
        } catch (Throwable $throwable) {
            return response()->json([
                'message' => 'This storage driver does not support temporary URLs.',
            ], Response::HTTP_BAD_REQUEST);
        }

        return response()->json([
            'file_id' => $file->id,
            'file_name' => $file->file_name,
            'download_url' => $temporaryUrl,
            'expires_at' => $expiresAt->toIso8601String(),
        ]);
    }

    public function uploadAttachment(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'file' => ['required', 'file'],
            'bucket_name' => ['required', 'string', 'max:255'],
        ]);

        $uploadedFile = $request->file('file');
        if (! $uploadedFile) {
            throw ValidationException::withMessages([
                'file' => ['The file field is required.'],
            ]);
        }

        $bucketName = trim($validated['bucket_name']);
        $directory = 'documents/'.now()->format('Y/m');
        $fileName = $uploadedFile->getClientOriginalName();
        $storedPath = trim($directory.'/'.$fileName, '/');

        Log::info('S3 upload attachment request received.', [
            'bucket_name' => $bucketName,
            'directory' => $directory,
            'file_name' => $fileName,
            'mime_type' => $uploadedFile->getClientMimeType(),
            'size' => $uploadedFile->getSize(),
            'client_ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'endpoint' => $request->path(),
        ]);

        try {
            $disk = Storage::build(array_merge(config('filesystems.disks.s3'), [
                'bucket' => $bucketName,
            ]));

            $storedResult = $disk->putFileAs($directory, $uploadedFile, $fileName, [
                'visibility' => 'private',
            ]);

            if (! $storedResult) {
                return response()->json([
                    'message' => 'Attachment upload failed.',
                    'error' => 'S3 rejected the file write operation.',
                ], Response::HTTP_BAD_GATEWAY);
            }
        } catch (Throwable $throwable) {
            Log::error('S3 attachment upload failed.', [
                'bucket_name' => $bucketName,
                'directory' => $directory,
                'file_name' => $fileName,
                'exception_class' => $throwable::class,
                'exception_message' => $throwable->getMessage(),
                'exception_code' => $throwable->getCode(),
                'exception_file' => $throwable->getFile(),
                'exception_line' => $throwable->getLine(),
                'trace' => $throwable->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Attachment upload failed.',
                'error' => 'Unable to write the file to S3. Check AWS credentials, bucket name, region, and endpoint configuration.',
            ], Response::HTTP_BAD_GATEWAY);
        }

        return response()->json([
            'message' => 'Attachment uploaded successfully.',
            'bucket_name' => $bucketName,
            'path' => $storedPath,
            'file_name' => $fileName,
        ], Response::HTTP_CREATED);
    }

    public function temporaryPublicUrl(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'path' => ['required', 'string', 'max:500'],
            'file_name' => ['required', 'string', 'max:255'],
            'bucket_name' => ['nullable', 'string', 'max:255'],
        ]);

        $bucketName = trim($validated['bucket_name'] ?? (string) config('filesystems.disks.s3.bucket'));
        $path = trim($validated['path'], '/');
        $fileName = ltrim($validated['file_name'], '/');
        $fullPath = trim($path.'/'.$fileName, '/');
        $expiresInMinutes = (int) config('filesystems.s3_temp_url_minutes', 15);
        $expiresAt = now()->addMinutes($expiresInMinutes);

        $disk = Storage::build(array_merge(config('filesystems.disks.s3'), [
            'bucket' => $bucketName,
        ]));

        try {
            $temporaryUrl = $disk->temporaryUrl($fullPath, $expiresAt);
        } catch (Throwable $throwable) {
            return response()->json([
                'message' => 'Unable to create temporary URL. Check that AWS credentials, bucket, and S3 settings are correct.',
            ], Response::HTTP_BAD_REQUEST);
        }

        return response()->json([
            'bucket_name' => $bucketName,
            'path' => $path,
            'file_name' => $fileName,
            'temporary_url' => $temporaryUrl,
            'expires_in_minutes' => $expiresInMinutes,
            'expires_at' => $expiresAt->toIso8601String(),
        ]);
    }
}
