<?php

namespace App\Repositories\Contracts;

use App\Models\User;

interface UserRepositoryInterface
{
    /** Persist a new user from validated, already-hashed attributes. */
    public function create(array $attributes): User;

    /** Resolve a user by email for login (null if none). */
    public function findByEmail(string $email): ?User;
}
