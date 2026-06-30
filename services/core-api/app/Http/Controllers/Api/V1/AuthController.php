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

/**
 * @group Auth
 *
 * Register, log in, and manage the current session.
 */
final class AuthController extends Controller
{
    public function __construct(
        private readonly AuthService $auth,
    ) {}

    /**
     * Register
     *
     * Create a new vendor or attendee account. Returns a Sanctum bearer token on success.
     * Admin accounts are provisioned via seeder only — the `admin` role is not self-assignable.
     *
     * @group Public
     *
     * @subgroup Auth
     *
     * @unauthenticated
     *
     * @bodyParam name string required Full name. Example: Alice Smith
     * @bodyParam email string required Email address. Example: alice@example.com
     * @bodyParam password string required Min 8 characters. Example: password123
     * @bodyParam password_confirmation string required Must match password. Example: password123
     * @bodyParam role string required `vendor` or `attendee`. Example: vendor
     * @bodyParam business_name string required if role is vendor. Example: Acme Events Ltd
     * @bodyParam phone string optional Attendee contact phone. Example: +8801711000000
     *
     * @response 201 scenario="Vendor registered" {"success":true,"message":"Account created successfully.","data":{"user":{"id":"01J0000000000000000VENDOR","name":"Acme Events Ltd","email":"vendor@eventhub.test","role":{"value":"vendor","label":"Vendor"},"created_at":"2026-06-30T10:00:00Z"},"token":"[PLACEHOLDER_TOKEN]"},"errors":null}
     */
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

    /**
     * Login
     *
     * Authenticate and receive a Sanctum bearer token. Use the returned token in an
     * `Authorization: Bearer {token}` header on subsequent requests.
     *
     * @group Public
     *
     * @subgroup Auth
     *
     * @unauthenticated
     *
     * @response 200 scenario="Success" {"success":true,"message":"Logged in successfully.","data":{"user":{"id":"01J0000000000000000VENDOR","name":"Acme Events Ltd","email":"vendor@eventhub.test","role":{"value":"vendor","label":"Vendor"},"created_at":"2026-06-30T10:00:00Z"},"token":"[PLACEHOLDER_TOKEN]"},"errors":null}
     * @response 401 scenario="Bad credentials" {"success":false,"message":"The provided credentials are incorrect.","data":null,"errors":null}
     */
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

    /**
     * Logout
     *
     * Revoke the current Sanctum token. The token becomes immediately invalid.
     *
     * @group Auth
     *
     * @authenticated
     */
    public function logout(Request $request): JsonResponse
    {
        LogHelper::landingLog($request, __CLASS__.' - '.__FUNCTION__);

        $this->auth->logout($request->user());

        return ApiResponse::success(message: __('api.auth.logged_out'));
    }

    /**
     * Current user
     *
     * Return the authenticated user along with their vendor or attendee profile.
     *
     * @group Auth
     *
     * @authenticated
     *
     * @response 200 scenario="Success" {"success":true,"message":"Authenticated user retrieved.","data":{"user":{"id":"01JWXYZ0000000000000VENDOR","name":"Acme Events Ltd","email":"vendor@eventhub.test","role":{"value":"vendor","label":"Vendor"},"created_at":"2026-06-30T10:00:00+00:00"}},"errors":null}
     */
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
