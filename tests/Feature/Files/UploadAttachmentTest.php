<?php

namespace Tests\Feature\Files;

use App\Http\Controllers\Api\FileController;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class UploadAttachmentTest extends TestCase
{
    public function test_it_uploads_attachment_to_the_requested_bucket(): void
    {
        $file = UploadedFile::fake()->create('contract.pdf', 12, 'application/pdf');

        $disk = Mockery::mock();
        $disk->shouldReceive('putFileAs')
            ->once()
            ->with('attachments/2026/06', Mockery::type(UploadedFile::class), 'contract.pdf', [
                'visibility' => 'private',
            ])
            ->andReturn('attachments/2026/06/contract.pdf');

        Storage::shouldReceive('build')
            ->once()
            ->andReturn($disk);

        $response = $this->postJson('/api/s3-files/upload-attachment', [
            'file' => $file,
            'bucket_name' => 'client-bucket',
            'directory' => 'attachments/2026/06',
        ]);

        $response->assertCreated();
        $response->assertJson([
            'message' => 'Attachment uploaded successfully.',
            'bucket_name' => 'client-bucket',
            'path' => 'attachments/2026/06/contract.pdf',
            'file_name' => 'contract.pdf',
        ]);
    }

    public function test_it_returns_bad_gateway_when_s3_write_fails(): void
    {
        $file = UploadedFile::fake()->create('broken.pdf', 12, 'application/pdf');

        Storage::shouldReceive('build')
            ->once()
            ->andThrow(new \RuntimeException('S3 unavailable'));

        $response = $this->postJson('/api/s3-files/upload-attachment', [
            'file' => $file,
            'bucket_name' => 'client-bucket',
        ]);

        $response->assertStatus(502);
        $response->assertJson([
            'message' => 'Attachment upload failed.',
        ]);
    }
}
