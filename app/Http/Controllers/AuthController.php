<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Spatie\Prometheus\Facades\Prometheus;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        if (! Auth::attempt($request->only('email', 'password'))) {
            Prometheus::getCounter('login_attempts_total')
                ->labels(['failed'])
                ->increment();

            throw ValidationException::withMessages([
                'email' => ['De opgegeven credentials zijn onjuist.'],
            ]);
        }

        Prometheus::getCounter('login_attempts_total')
            ->labels(['success'])
            ->increment();

        $user = Auth::user();
        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Uitgelogd']);
    }

    public function user(Request $request)
    {
        return response()->json($request->user());
    }
}
