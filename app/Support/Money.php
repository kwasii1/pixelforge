<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * All money in the app is an integer number of nanodollars (1 USD = 1,000,000,000).
 *
 * Pixelforge reports exact decimal strings with up to nine decimal places, so
 * nanodollars hold every amount exactly and money never touches a float.
 */
final class Money
{
    public const NANODOLLARS_PER_DOLLAR = 1_000_000_000;

    private const DECIMAL_PLACES = 9;

    /**
     * Parse an exact decimal dollar string, such as "12.50" or "0.046125000", into nanodollars.
     */
    public static function fromDecimal(string $amount): int
    {
        if (! preg_match('/^(-?)(\d+)(?:\.(\d{1,9}))?$/', trim($amount), $matches)) {
            throw new InvalidArgumentException("[{$amount}] is not a dollar amount with at most nine decimal places.");
        }

        $nanodollars = (int) $matches[2] * self::NANODOLLARS_PER_DOLLAR
            + (int) str_pad($matches[3] ?? '', self::DECIMAL_PLACES, '0');

        return $matches[1] === '-' ? -$nanodollars : $nanodollars;
    }

    /**
     * Render nanodollars as an exact decimal string with nine places, the format Pixelforge uses.
     */
    public static function toDecimal(int $nanodollars): string
    {
        $sign = $nanodollars < 0 ? '-' : '';
        $absolute = abs($nanodollars);

        return sprintf(
            '%s%d.%09d',
            $sign,
            intdiv($absolute, self::NANODOLLARS_PER_DOLLAR),
            $absolute % self::NANODOLLARS_PER_DOLLAR,
        );
    }

    /**
     * Render nanodollars for people, e.g. "$0.0302", rounding half up to the given places.
     *
     * A non-zero amount that would round to zero is shown as "<$0.0001" so it never looks free.
     */
    public static function format(int $nanodollars, int $decimals = 4): string
    {
        $unit = 10 ** (self::DECIMAL_PLACES - $decimals);
        $absolute = abs($nanodollars);
        $rounded = intdiv($absolute + intdiv($unit, 2), $unit);
        $sign = $nanodollars < 0 ? '-' : '';

        if ($rounded === 0 && $absolute > 0) {
            return sprintf('%s<$0.%s1', $sign, str_repeat('0', $decimals - 1));
        }

        $scale = 10 ** $decimals;

        return sprintf(
            '%s$%s.%0'.$decimals.'d',
            $sign,
            number_format(intdiv($rounded, $scale)),
            $rounded % $scale,
        );
    }
}
