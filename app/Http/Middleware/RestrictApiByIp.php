<?php

namespace App\Http\Middleware;

use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpFoundation\Response;

class RestrictApiByIp
{
    public function handle(Request $request, Closure $next): Response
    {
        $allowlist = config('api.allowlisted_ips', []);

        if (! is_array($allowlist)) {
            $allowlist = [];
        }

        $allowlist = array_values(array_filter(array_map(
            static fn ($ip) => trim((string) $ip),
            $allowlist
        )));

        if ($allowlist === []) {
            return $next($request);
        }

        $ip = (string) $request->ip();

        foreach ($allowlist as $allowed) {
            if (IpUtils::checkIp($ip, $allowed)) {
                return $next($request);
            }
        }

        return ApiResponse::error(
            code: 'IP_NOT_ALLOWED',
            message: 'Access to this API is restricted from the current IP address.',
            status: 403
        );
    }
}
