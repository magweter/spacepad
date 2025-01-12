<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

abstract class AuthController extends Controller
{
    protected function issueToken(string $tokenName): string
    {
        /** @var User $user */
        $user = auth()->user();

        return $user->createToken($tokenName)->plainTextToken;
    }

    protected function createUser(
        string $name,
        string $email,
        string $password = null
    ): User {
        $attributes = [
            'name' => $name,
            'email' => $email,
            'password' => $password ? Hash::make($password) : null,
        ];

        return User::factory()->create($attributes);
    }
}
