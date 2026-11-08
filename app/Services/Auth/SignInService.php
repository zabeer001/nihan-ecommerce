<?php

namespace App\Services\Auth;

use App\Exceptions\FrozenAccountException;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\Auth;

class SignInService
{
    /**
     * @param array<string, string> $credentials
     * @return array<string, mixed>
     */
    public function __invoke(array $credentials): array
    {
        $token = Auth::guard('api')->attempt($credentials);

        if (! $token) {
            throw new Exception('Invalid credentials.');
        }

        /** @var User|null $user */
        $user = Auth::guard('api')->user();

        if ($user?->is_frozen) {
            Auth::guard('api')->logout();

            throw new FrozenAccountException('This account has been frozen. Please contact an administrator.');
        }

        return [
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => config('jwt.ttl') * 60,
            'user' => $user,
        ];
    }
}
