<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Throwable;

class S3FileToolController extends Controller
{
    public function index(Request $request): View
    {
        return view('s3-files', [
            'uploadedUrl' => $request->session()->get('uploaded_url'),
            'temporaryUrl' => $request->session()->get('temporary_url'),
            'errorMessage' => $request->session()->get('error_message'),
            'savedPath' => $request->session()->get('saved_path'),
            'savedDisk' => $request->session()->get('saved_disk', 's3'),
            'savedName' => $request->session()->get('saved_name'),
            'expiresAt' => $request->session()->get('expires_at'),
        ]);
    }

    public function upload(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'file' => ['required', 'file'],
        ]);

        $file = $validated['file'];
        $directory = 'documents/'.now()->format('Y/m');
        $fileName = $file->getClientOriginalName();
        $path = Storage::disk('s3')->putFileAs($directory, $file, $fileName, [
            'visibility' => 'private',
        ]);

        return redirect()
            ->route('s3-files.index')
            ->with('uploaded_url', $path ? "Saved to S3 at: {$path}" : null)
            ->with('saved_path', $path)
            ->with('saved_disk', 's3')
            ->with('saved_name', $fileName);
    }

    public function temporaryUrl(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'path' => ['required', 'string', 'max:500'],
            'file_name' => ['required', 'string', 'max:255'],
            'expires_in' => ['nullable', 'integer', 'min:1', 'max:10080'],
        ]);

        $path = trim($validated['path'], '/');
        $fileName = ltrim($validated['file_name'], '/');
        $fullPath = $path.'/'.$fileName;
        $expiresIn = (int) ($validated['expires_in'] ?? 15);

        try {
            $temporaryUrl = Storage::disk('s3')->temporaryUrl($fullPath, now()->addMinutes($expiresIn));
        } catch (Throwable $throwable) {
            return redirect()
                ->route('s3-files.index')
                ->with('error_message', 'Unable to create temporary URL. Check that AWS credentials, bucket, and S3 settings are correct.');
        }

        return redirect()
            ->route('s3-files.index')
            ->with('temporary_url', $temporaryUrl)
            ->with('saved_path', $fullPath)
            ->with('saved_disk', 's3')
            ->with('saved_name', $fileName)
            ->with('expires_at', now()->addMinutes($expiresIn)->toIso8601String());
    }
}
