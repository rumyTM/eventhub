<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\LogHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Services\Auth\AuthService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AuthController extends Controller
{
    public function __construct(
        private readonly AuthService $auth,
    ) {}

    /** Register a vendor or attendee; creates the role profile and returns a bearer token. */
    public function register(RegisterRequest $request): JsonResponse
    {
        LogHelper::landingLog($request, __CLASS__.' - '.__FUNCTION__);

        $result = $this->auth->register($request->validated());

        return ApiResponse::success(
            data: [
                'user' => new UserResource($result['user']),
                'token' => $result['token'],
            ],
            message: __('api.auth.registered'),
            status: 201,
        );
    }

    /** Verify credentials and return a bearer token. */
    public function login(LoginRequest $request): JsonResponse
    {
        LogHelper::landingLog($request, __CLASS__.' - '.__FUNCTION__);

        $result = $this->auth->login(
            email: $request->validated('email'),
            password: $request->validated('password'),
        );

        return ApiResponse::success(
            data: [
                'user' => new UserResource($result['user']),
                'token' => $result['token'],
            ],
            message: __('api.auth.logged_in'),
        );
    }

    /** Revoke the current access token. */
    public function logout(Request $request): JsonResponse
    {
        LogHelper::landingLog($request, __CLASS__.' - '.__FUNCTION__);

        $this->auth->logout($request->user());

        return ApiResponse::success(message: __('api.auth.logged_out'));
    }

    /** Return the authenticated user with their role profile. */
    public function me(Request $request): JsonResponse
    {
        LogHelper::landingLog($request, __CLASS__.' - '.__FUNCTION__);

        $user = $request->user();
        $user->load($user->isVendor() ? 'vendor' : ($user->isAttendee() ? 'attendee' : []));

        return ApiResponse::success(
            data: ['user' => new UserResource($user)],
            message: __('api.auth.me'),
        );
    }
}
