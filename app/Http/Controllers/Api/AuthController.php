<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\User;
use App\Services\LoginRateLimitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(
        private readonly LoginRateLimitService $rateLimit
    ) {}

    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $rateKey = 'api-login|'.$request->ip().'|'.mb_strtolower($credentials['email']);

        if ($this->rateLimit->tooManyAttempts($rateKey)) {
            throw ValidationException::withMessages([
                'email' => [$this->rateLimit->lockoutMessage($rateKey)],
            ]);
        }

        $user = User::query()->where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            $this->rateLimit->hit($rateKey);

            throw ValidationException::withMessages([
                'email' => [$this->rateLimit->failedLoginMessage($rateKey)],
            ]);
        }

        $this->rateLimit->clear($rateKey);

        if ($user->must_change_password ?? false) {
            return response()->json([
                'message' => 'Pehle web par naya password set karein.',
            ], 403);
        }

        if (! in_array($user->role ?? null, ['super_admin', 'company_admin', 'admin'], true)) {
            $ok = Employee::query()
                ->where('user_id', $user->id)
                ->where('active', true)
                ->exists();

            if (! $ok) {
                return response()->json(['message' => 'Active employee account required.'], 403);
            }
        }

        if (! $user->bypassesModulePermissions() && ! $user->touchesModule('order-taker')) {
            return response()->json(['message' => 'Order Taker module access nahi hai.'], 403);
        }

        $token = $user->createToken('order-taker-mobile')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json(['message' => 'Logged out']);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
            ],
        ]);
    }
}
