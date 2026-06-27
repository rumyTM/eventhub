<?php

namespace App\Repositories\Eloquent;

use App\Models\Setting;
use App\Repositories\Contracts\SettingRepositoryInterface;

final class SettingRepository implements SettingRepositoryInterface
{
    public function get(string $key): ?string
    {
        return Setting::query()->where('key', $key)->value('value');
    }
}
