<?php

namespace Tests\Concerns;

use App\Models\User;

trait InteractsWithApiTokens
{
    protected function bearer(User $user): string
    {
        return $user->createToken('auth')->plainTextToken;
    }
}
