<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Support\MobileAuthPayload;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

class AuthController extends ApiController
{
    public function login(Request $request)
    {
        $data = $request->validate([
            'email'       => 'required|email',
            'password'    => 'required|string',
            'device_name' => 'required|string|max:255',
        ]);

        if (! Auth::attempt(['email' => $data['email'], 'password' => $data['password']])) {
            throw ValidationException::withMessages([
                'email' => ['Invalid credentials.'],
            ]);
        }

        $user = $request->user()->fresh();

        if ($message = MobileAuthPayload::assertCanLogin($user)) {
            Auth::logout();

            throw ValidationException::withMessages([
                'email' => [$message],
            ]);
        }

        $token = $user->createToken($data['device_name'])->plainTextToken;

        return $this->ok(array_merge(
            MobileAuthPayload::for($user),
            ['token' => $token],
        ));
    }

    public function logout(Request $request)
    {
        if ($bearer = $request->bearerToken()) {
            PersonalAccessToken::findToken($bearer)?->delete();
        } else {
            $request->user()?->currentAccessToken()?->delete();
        }

        Auth::guard('web')->logout();

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return $this->message('Logged out.');
    }

    public function me(Request $request)
    {
        return $this->ok(MobileAuthPayload::for($request->user()->fresh()));
    }
}
