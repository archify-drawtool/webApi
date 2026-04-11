<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Prometheus\CollectorRegistry;

class AuthController extends Controller
{
    public function __construct(private CollectorRegistry $registry) {}

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        if (! Auth::attempt($request->only('email', 'password'))) {
            // Mislukte login
            $this->registry
                ->getOrRegisterCounter('app', 'login_attempts_total', 'Aantal inlogpogingen', ['status'])
                ->inc(['failed']);

            throw ValidationException::withMessages([
                'email' => ['De opgegeven credentials zijn onjuist.'],
            ]);
        }

        // Succesvolle login
        $this->registry
            ->getOrRegisterCounter('app', 'login_attempts_total', 'Aantal inlogpogingen', ['status'])
            ->inc(['success']);

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
