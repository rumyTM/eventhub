<?php

namespace App\Repositories\Contracts;

interface SettingRepositoryInterface
{
    /** Raw string value for a platform setting key, or null if unset. */
    public function get(string $key): ?string;
}
