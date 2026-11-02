<?php

namespace App\Services;

use App\Models\AppSetting;

/**
 * The application-wide settings the Super Admin keeps. Each has a default that holds until it is changed.
 */
class AppSettingService
{
    private const PASSWORD_MIN_LENGTH_KEY = 'password_min_length';

    /** Short on purpose: cashiers type their password at the till. The Super Admin can raise it. */
    public const DEFAULT_PASSWORD_MIN_LENGTH = 5;

    /** The lowest value the setting accepts, so it can never be lowered under what was agreed. */
    public const LOWEST_PASSWORD_MIN_LENGTH = 5;

    public const HIGHEST_PASSWORD_MIN_LENGTH = 64;

    /**
     * How many characters a user's password needs at least, when a user is made or its password changed.
     */
    public function passwordMinLength(): int
    {
        $storedLength = AppSetting::query()->where('key', self::PASSWORD_MIN_LENGTH_KEY)->value('value');

        return $storedLength === null ? self::DEFAULT_PASSWORD_MIN_LENGTH : (int) $storedLength;
    }

    public function setPasswordMinLength(int $length): void
    {
        AppSetting::query()->updateOrCreate(['key' => self::PASSWORD_MIN_LENGTH_KEY], ['value' => (string) $length]);
    }
}
