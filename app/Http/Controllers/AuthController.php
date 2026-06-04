<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\EntraTokenVerifier;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function microsoftLogin(Request $request, EntraTokenVerifier $verifier)
    {
        $request->validate(['id_token' => 'required|string']);

        try {
            $claims = $verifier->verify($request->id_token);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Invalid Microsoft token'], 401);
        }

        // Zoek of maak user aan op microsoft_id (= 'oid' claim)
        $user = User::firstOrNew(['microsoft_id' => $claims['oid']]);
        $user->email = $claims['preferred_username'] ?? $claims['email'];
        $user->name = $claims['name'] ?? $user->email;
        $user->save();

        $token = $user->createToken('archify')->plainTextToken;

        return response()->json(['token' => $token, 'user' => $user]);
    }

    public function me(Request $request)
    {
        return response()->json($request->user());
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out']);
    }

    public function updatePreferences(Request $request)
    {
        $validated = $request->validate([
            'show_background_dots' => 'required|boolean',
        ]);

        $request->user()->update($validated);

        return response()->json($request->user()->fresh());
    }
}
