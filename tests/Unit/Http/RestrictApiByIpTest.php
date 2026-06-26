<?php

namespace Tests\Unit\Http;

use App\Http\Middleware\RestrictApiByIp;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Tests\TestCase;

class RestrictApiByIpTest extends TestCase
{
    public function test_it_allows_requests_when_allowlist_is_empty(): void
    {
        config(['api.allowlisted_ips' => []]);

        $request = Request::create('/api/s3-files/upload-attachment', 'POST');
        $request->server->set('REMOTE_ADDR', '203.0.113.10');

        $response = (new RestrictApiByIp())->handle($request, fn () => new Response('ok', 200));

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_it_blocks_requests_from_non_allowlisted_ips(): void
    {
        config(['api.allowlisted_ips' => ['198.51.100.0/24']]);

        $request = Request::create('/api/s3-files/upload-attachment', 'POST');
        $request->server->set('REMOTE_ADDR', '203.0.113.10');

        $response = (new RestrictApiByIp())->handle($request, fn () => new Response('ok', 200));

        $this->assertSame(403, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertSame('IP_NOT_ALLOWED', $data['error']['code'] ?? null);
    }
}
