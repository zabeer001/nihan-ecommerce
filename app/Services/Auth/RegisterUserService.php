<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class RegisterUserService
{
    /**
     * @param array<string, string> $attributes
     * @return array<string, mixed>
     */
    public function __invoke(array $attributes): array
    {
        return DB::transaction(function () use ($attributes): array {
            $user = User::create([
                'name' => $attributes['name'],
                'email' => $attributes['email'],
                'password' => Hash::make($attributes['password']),
                'role' => 'user',
                'is_frozen' => false,
            ]);

            $token = Auth::guard('api')->login($user);

            return [
                'access_token' => $token,
                'token_type' => 'bearer',
                'expires_in' => config('jwt.ttl') * 60,
                'user' => Auth::guard('api')->user(),
            ];
        });
    }
}
