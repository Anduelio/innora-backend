<?php

namespace App\Http\Middleware;

use App\Enums\PermissionEnum;
use App\Enums\UserRole;
use App\Helpers\ApiCodes;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route-level permission gate. Owner short-circuits. Other roles must match
 * one of the pipe-separated PermissionEnum values on the role map.
 */
class EnsurePermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        /** @var User|null $user */
        $user = $request->user();

        if ($user === null) {
            return response()->json([
                'success' => false,
                'message' => ApiCodes::getWrongCredentialsMessage(),
            ], ApiCodes::UNAUTHENTICATED);
        }

        $role = UserRole::tryFrom((string) $user->role?->code);
        if ($role === UserRole::Owner) {
            return $next($request);
        }

        $needed = array_filter(explode('|', $permission));
        foreach ($needed as $code) {
            $enum = PermissionEnum::tryFrom($code);
            if ($enum !== null && $user->canPermission($enum)) {
                return $next($request);
            }
        }

        return response()->json([
            'success' => false,
            'message' => ApiCodes::getForbiddenErrorMessage(),
        ], ApiCodes::FORBIDDEN);
    }
}
