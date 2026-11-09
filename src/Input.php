<?php
declare(strict_types=1);
namespace Passport;

final class Input
{
    public static function text(array $data, string $key, int $max = 200, bool $required = true): string
    {
        $value = $data[$key] ?? '';
        if (!is_string($value) || mb_strlen(trim($value)) > $max || ($required && trim($value) === '')) {
            throw new Problem('VALIDATION', 'Please check ' . $key . '.', 422, [$key => 'A valid value is required (maximum ' . $max . ' characters).']);
        }
        return trim($value);
    }

    public static function choice(array $data, string $key, array $choices): string
    {
        $value = self::text($data, $key);
        if (!in_array($value, $choices, true)) {
            throw new Problem('VALIDATION', 'Choose a valid ' . $key . '.', 422, [$key => 'Unsupported value.']);
        }
        return $value;
    }

    public static function bool(array $data, string $key): bool
    {
        if (!isset($data[$key])) {
            return false;
        }
        if (!is_bool($data[$key])) {
            throw new Problem('VALIDATION', $key . ' must be true or false.');
        }
        return $data[$key];
    }

    public static function money(array $data, string $key, bool $signed = false): ?int
    {
        $value = $data[$key] ?? null;
        if ($value === null) {
            return null;
        }
        if (!is_int($value) || $value < ($signed ? -Rules::MAX_MONEY : 0) || $value > Rules::MAX_MONEY) {
            throw new Problem('INVALID_AMOUNT', 'Enter a valid whole number of MOCK minor units.');
        }
        return $value;
    }

    public static function email(array $data, string $key): string
    {
        $value = self::text($data, $key, 254);
        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            throw new Problem('VALIDATION', 'Enter a valid email address.', 422, [$key => 'Invalid email.']);
        }
        return $value;
    }

    public static function currency(array $data): string
    {
        $value = self::text($data + ['currency' => 'ZAR'], 'currency', 3);
        if (!in_array($value, ['ZAR', 'USD', 'EUR', 'GBP', 'KES', 'NGN'], true)) {
            throw new Problem('VALIDATION', 'Choose a supported currency.');
        }
        return $value;
    }
}
