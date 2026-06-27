<?php

namespace App\Services\Auth;

use App\Enums\Role;
use App\Exceptions\Auth\InvalidCredentialsException;
use App\Models\User;
use App\Repositories\Contracts\AttendeeRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Repositories\Contracts\VendorRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Auth orchestration: registration (user + role profile created atomically), login, logout, and
 * token issuance. Holds the transaction boundary; data access goes through repositories.
 */
final class AuthService
{
    private const TOKEN_NAME = 'api';

    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly VendorRepositoryInterface $vendors,
        private readonly AttendeeRepositoryInterface $attendees,
    ) {}

    /**
     * Create the user and its matching role profile in one transaction, then issue an API token.
     *
     * @param  array<string, mixed>  $data  validated registration payload
     * @return array{user: User, token: string}
     */
    public function register(array $data): array
    {
        $user = DB::transaction(function () use ($data): User {
            $role = Role::from($data['role']);

            $user = $this->users->create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'], // hashed by the User 'password' => 'hashed' cast
                'role' => $role,
            ]);

            // Each role owns exactly one profile row (admins have no profile table).
            match ($role) {
                Role::Vendor => $this->vendors->createForUser($user, [
                    'business_name' => $data['business_name'],
                ]),
                Role::Attendee => $this->attendees->createForUser($user, [
                    'phone' => $data['phone'] ?? null,
                ]),
                Role::Admin => null,
            };

            return $user;
        });

        return [
            'user' => $user->load($user->isVendor() ? 'vendor' : 'attendee'),
            'token' => $this->issueToken($user),
        ];
    }

    /**
     * Verify credentials and issue a token. Throws on any mismatch without revealing which field failed.
     *
     * @return array{user: User, token: string}
     */
    public function login(string $email, string $password): array
    {
        $user = $this->users->findByEmail($email);

        if ($user === null || ! Hash::check($password, $user->password)) {
            throw new InvalidCredentialsException;
        }

        return [
            'user' => $user,
            'token' => $this->issueToken($user),
        ];
    }

    /** Revoke only the token used for the current request (other sessions stay valid). */
    public function logout(User $user): void
    {
        $user->currentAccessToken()?->delete();
    }

    private function issueToken(User $user): string
    {
        return $user->createToken(self::TOKEN_NAME)->plainTextToken;
    }
}
