<?php

namespace App\Http\Middleware;

use App\Models\WorkspaceUser;
use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureWorkspaceScope
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user) {
            return ApiResponse::error(
                code: 'UNAUTHENTICATED',
                message: 'Authentication is required.',
                status: 401
            );
        }

        $isSuperAdmin = method_exists($user, 'hasRole') && $user->hasRole('super_admin');

        $fromHeader = (int) $request->header('X-Workspace-Id', $request->input('workspace_id', 0));
        $fromRoute = $this->workspaceIdFromRoute($request);

        if (! $isSuperAdmin) {
            $activeMemberships = WorkspaceUser::query()
                ->where('user_id', $user->id)
                ->where('status', 'active');

            $activeWorkspaceIds = $activeMemberships->pluck('workspace_id')->values();
            if ($activeWorkspaceIds->isEmpty()) {
                return ApiResponse::error(
                    code: 'WORKSPACE_ACCESS_DENIED',
                    message: 'You do not have an active workspace.',
                    status: 403
                );
            }

            if ($activeWorkspaceIds->count() > 1) {
                return ApiResponse::error(
                    code: 'MULTIPLE_WORKSPACES_NOT_SUPPORTED',
                    message: 'User is linked to multiple workspaces. Please keep one workspace per user.',
                    status: 409
                );
            }

            $derivedWorkspaceId = (int) $activeWorkspaceIds->first();

            if ($fromRoute > 0 && $fromRoute !== $derivedWorkspaceId) {
                return ApiResponse::error(
                    code: 'WORKSPACE_BOUNDARY_VIOLATION',
                    message: 'Requested workspace does not match user workspace.',
                    status: 403
                );
            }

            if ($fromHeader > 0 && $fromHeader !== $derivedWorkspaceId) {
                return ApiResponse::error(
                    code: 'WORKSPACE_BOUNDARY_VIOLATION',
                    message: 'Requested workspace does not match user workspace.',
                    status: 403
                );
            }

            $workspaceId = $derivedWorkspaceId;
        } else {
            if ($fromHeader > 0 && $fromRoute > 0 && $fromHeader !== $fromRoute) {
                return ApiResponse::error(
                    code: 'WORKSPACE_BOUNDARY_VIOLATION',
                    message: 'Workspace scope from X-Workspace-Id does not match the workspace in the URL.',
                    status: 403
                );
            }

            $workspaceId = $fromHeader > 0 ? $fromHeader : $fromRoute;

            if ($workspaceId <= 0 && $request->is('api/dashboard')) {
                // Super admins can open the global dashboard without selecting a workspace.
                $request->attributes->set('workspace_id', 0);

                return $next($request);
            }

            if ($workspaceId <= 0) {
                return ApiResponse::error(
                    code: 'WORKSPACE_SCOPE_REQUIRED',
                    message: 'Workspace scope is required for super admin requests (X-Workspace-Id, workspace_id query, or a workspace id in the URL for routes that include it).',
                    status: 400
                );
            }
        }

        $request->attributes->set('workspace_id', $workspaceId);

        foreach ($request->route()?->parameters() ?? [] as $parameter) {
            if (! is_object($parameter) || ! isset($parameter->workspace_id)) {
                continue;
            }

            if ((int) $parameter->workspace_id !== $workspaceId) {
                return ApiResponse::error(
                    code: 'WORKSPACE_BOUNDARY_VIOLATION',
                    message: 'Cross-workspace access is not allowed.',
                    status: 403
                );
            }
        }

        return $next($request);
    }

    private function workspaceIdFromRoute(Request $request): int
    {
        $route = $request->route();
        if ($route === null) {
            return 0;
        }

        $params = $route->parameters();

        foreach (['workspace', 'workspace_id'] as $key) {
            if (! array_key_exists($key, $params)) {
                continue;
            }

            $value = $params[$key];
            if ($value === null) {
                continue;
            }

            if (is_object($value) && isset($value->id)) {
                return (int) $value->id;
            }

            if (is_numeric($value)) {
                return (int) $value;
            }
        }

        return 0;
    }
}
