<?php

namespace App\Services\Auth;

use Illuminate\Support\Facades\Auth;

class SignOutService
{
    public function __invoke(): string
    {
        Auth::guard('api')->logout();

        return 'Signed out successfully.';
    }
}
