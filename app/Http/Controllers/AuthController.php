<?php

namespace App\Http\Controllers;

use App\Http\Requests\ChangePasswordRequest;
use App\Http\Requests\RefreshTokenRequest;
use App\Http\Resources\AuthorizationResource;
use App\Http\Resources\LoginResource;
use App\Http\Resources\UserResource;
use App\Managers\Auth\AuthManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(private readonly AuthManager $auth) {}

    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $result = $this->auth->login($credentials);

        if ($result === false) {
            return $this->generalError(...$this->auth->getError());
        }

        return $this->getJsonResponse(
            $result,
            LoginResource::class,
            'messages.success.the_action_was_completed_successfully',
            true,
        );
    }

    public function me(Request $request): JsonResponse
    {
        return $this->getJsonResponse(
            $this->auth->userInfo($request->user()),
            UserResource::class,
            asSingleModel: true,
        );
    }

    public function logout(Request $request): JsonResponse
    {
        return $this->auth->logout($request->user())
            ? $this->successResponse('messages.success.logged_out_successfully')
            : $this->generalError();
    }

    public function refresh(RefreshTokenRequest $request): JsonResponse
    {
        $result = $this->auth->refreshToken($request->validated());

        if ($result === false) {
            return $this->generalError(...$this->auth->getError());
        }

        return $this->responseToJson(
            'messages.success.the_action_was_completed_successfully',
            200,
            ['authorization' => (new AuthorizationResource($result))->resolve()],
        );
    }

    public function password(ChangePasswordRequest $request): JsonResponse
    {
        $user = $this->auth->changePassword($request->user(), $request->validated());

        return $this->getJsonResponse(
            $user,
            UserResource::class,
            'messages.auth.password_updated',
            true,
        );
    }
}
