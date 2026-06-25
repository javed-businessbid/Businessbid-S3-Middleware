<?php

namespace Tests\Unit\Http;

use App\Http\Middleware\RejectFailedFileUploads;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Tests\TestCase;

class RejectFailedFileUploadsTest extends TestCase
{
    public function test_it_returns_json_when_php_rejects_file_size(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'ul');
        $this->assertNotFalse($tmp);

        $file = new UploadedFile($tmp, 'logo.png', 'image/png', UPLOAD_ERR_INI_SIZE, true);

        $request = Request::create('/api/workspaces', 'POST', [], [], ['logo' => $file]);

        $middleware = new RejectFailedFileUploads;
        $response = $middleware->handle($request, fn () => new Response('ok', 200));

        $this->assertSame(422, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertSame('FILE_UPLOAD_FAILED', $data['error']['code'] ?? null);
        $this->assertArrayHasKey('logo', (array) ($data['error']['fields'] ?? []));
    }

    public function test_it_allows_upload_err_no_file_for_optional_fields(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'ul');
        $this->assertNotFalse($tmp);

        $file = new UploadedFile($tmp, 'logo.png', 'image/png', UPLOAD_ERR_NO_FILE, true);

        $request = Request::create('/api/workspaces', 'POST', [], [], ['logo' => $file]);

        $middleware = new RejectFailedFileUploads;
        $response = $middleware->handle($request, fn () => new Response('ok', 200));

        $this->assertSame(200, $response->getStatusCode());
    }
}
