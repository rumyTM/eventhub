<?php

namespace App\Models;

use Database\Factories\SettingFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Admin-configurable platform values (commission rate, minimum payout threshold). Read at sale time and
 * snapshotted onto orders, so editing a setting only affects future sales. Updated in place.
 */
class Setting extends Model
{
    /** @use HasFactory<SettingFactory> */
    use HasFactory, HasUlids;

    /** @var list<string> */
    protected $fillable = [
        'key',
        'value',
        'type',
    ];
}
