<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppSetting extends Model
{
    protected $fillable = [
        'key',
        'value',
    ];

    public static function getValue(string $key, ?string $default = null): ?string
    {
        try {
            $setting = static::where('key', $key)->first();
            if ($setting && $setting->value !== null && trim($setting->value) !== '') {
                return $setting->value;
            }
        } catch (\Throwable $e) {
            // Table may not exist during early bootstrap or isolated tests
        }

        return $default;
    }

    public static function setValue(string $key, ?string $value): self
    {
        return static::updateOrCreate(
            ['key' => $key],
            ['value' => $value]
        );
    }
}
