<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\ApiLoginRequest;
use App\Http\Requests\Api\V1\Auth\ApiRegisterRequest;
use App\Http\Resources\Api\V1\CustomerResource;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class CustomerAuthController extends Controller
{
    public function register(ApiRegisterRequest $request): JsonResponse
    {
        $user = User::create([
            'name' => $request->string('name')->toString(),
            'email' => $request->string('email')->toString(),
            'password' => Hash::make($request->string('password')->toString()),
        ]);

        event(new Registered($user));

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return response()->json([
            'data' => CustomerResource::make($user)->resolve($request),
        ], 201);
    }

    public function login(ApiLoginRequest $request): JsonResponse
    {
        try {
            $request->authenticate();
        } catch (ValidationException) {
            $rateLimited = RateLimiter::tooManyAttempts($request->throttleKey(), 5);

            return response()->json([
                'message' => $rateLimited
                    ? 'Too many login attempts. Please try again later.'
                    : 'The provided credentials are incorrect.',
                'errors' => [
                    'email' => [$rateLimited ? 'Too many login attempts.' : 'The provided credentials are incorrect.'],
                ],
                'code' => $rateLimited ? 'rate_limited' : 'invalid_credentials',
            ], $rateLimited ? 429 : 422);
        }

        $request->session()->regenerate();

        return response()->json([
            'data' => CustomerResource::make($request->user('web'))->resolve($request),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['data' => null]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'data' => CustomerResource::make($request->user('web'))->resolve($request),
        ]);
    }
}
