<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UpsertUserPasswordCommand extends Command
{
    protected $signature = 'user:upsert {email : User email address} {password : New user password}';

    protected $description = 'Create a user or update the password when the email already exists.';

    public function handle(): int
    {
        $email = Str::lower(trim((string) $this->argument('email')));
        $password = (string) $this->argument('password');

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Please provide a valid email address.');

            return self::FAILURE;
        }

        if ($password === '') {
            $this->error('Password cannot be empty.');

            return self::FAILURE;
        }

        $user = User::query()->where('email', $email)->first();

        if ($user) {
            $user->forceFill([
                'password' => Hash::make($password),
            ])->save();

            $this->info("Password updated for {$email}.");

            return self::SUCCESS;
        }

        $name = Str::of($email)->before('@')->replace(['.', '_', '-'], ' ')->title()->toString();

        (new User())->forceFill([
            'name' => $name ?: 'New User',
            'email' => $email,
            'role' => 'user',
            'status' => 'active',
            'password' => Hash::make($password),
        ])->save();

        $this->info("User created for {$email}.");

        return self::SUCCESS;
    }
}
