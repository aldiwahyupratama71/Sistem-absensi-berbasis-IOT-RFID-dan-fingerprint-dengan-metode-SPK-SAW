<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * POST /api/auth/login
     * Autentikasi HRD dan kembalikan Bearer token Sanctum.
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ], [
            'email.required'    => 'Email wajib diisi.',
            'email.email'       => 'Format email tidak valid.',
            'password.required' => 'Password wajib diisi.',
        ]);

        // Rate limiting: maks 5 percobaan per menit per IP+email
        $throttleKey = $this->throttleKey($request);

        if (RateLimiter::tooManyAttempts($throttleKey, maxAttempts: 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            return response()->json([
                'message' => "Terlalu banyak percobaan login. Coba lagi dalam {$seconds} detik.",
            ], 429);
        }

        if (! Auth::attempt($request->only('email', 'password'))) {
            RateLimiter::hit($throttleKey, 60);

            return response()->json([
                'message' => 'Email atau password salah.',
            ], 401);
        }

        RateLimiter::clear($throttleKey);

        /** @var User $user */
        $user = Auth::user();

        // Cabut semua token lama agar sesi bersih
        $user->tokens()->delete();

        $token = $user->createToken('hrd-session')->plainTextToken;

        return response()->json([
            'message' => 'Login berhasil.',
            'token'   => $token,
            'user'    => [
                'id'    => $user->id,
                'name'  => $user->name,
                'email' => $user->email,
                'role'  => $user->role,
            ],
        ]);
    }

    /**
     * POST /api/auth/logout
     * Cabut token yang sedang digunakan.
     */
    public function logout(Request $request): JsonResponse
    {
        $token = $request->user()->currentAccessToken();

        // TransientToken digunakan oleh Sanctum saat testing (actingAs).
        // Di production, ini selalu PersonalAccessToken yang bisa di-delete.
        if (! ($token instanceof \Laravel\Sanctum\TransientToken)) {
            $token->delete();
        }

        return response()->json([
            'message' => 'Logout berhasil.',
        ]);
    }

    /**
     * GET /api/auth/me
     * Kembalikan data user yang sedang login.
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'id'    => $user->id,
            'name'  => $user->name,
            'email' => $user->email,
            'role'  => $user->role,
        ]);
    }

    /**
     * Buat throttle key unik per IP + email.
     */
    private function throttleKey(Request $request): string
    {
        return Str::lower($request->input('email', '')).'|'.$request->ip();
    }
}
