<?php

namespace App\Services\Auth;

use App\Exceptions\FrozenAccountException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Auth;

class RefreshTokenService
{
    /**
     * @return array<string, mixed>
     */
    public function __invoke(): array
    {
        $guard = Auth::guard('api');
        $token = $guard->refresh();
        $user = $guard->setToken($token)->user();
        if (! $user || $user->is_frozen) {
            $guard->logout();
            if ($user?->is_frozen) {
                throw new FrozenAccountException('This account has been frozen.');
            }
            throw new AuthenticationException('Account no longer exists.');
        }

        return [
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => config('jwt.ttl') * 60,
        ];
    }
}
