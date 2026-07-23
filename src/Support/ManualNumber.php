<?php

namespace MuhammadMahediHasan\UserManual\Support;

use NumberFormatter;

class ManualNumber
{
    /**
     * Convert ASCII digits in text or numbers to localized digits for any given locale.
     */
    public static function formatDigits(string|int|float $value, string $locale): string
    {
        if (class_exists('NumberFormatter')) {
            $formatter = new NumberFormatter($locale, NumberFormatter::DECIMAL);
            $formatter->setAttribute(NumberFormatter::GROUPING_USED, false);

            return (string) preg_replace_callback('/\d+/', function ($matches) use ($formatter) {
                return $formatter->format((int) $matches[0]);
            }, (string) $value);
        }

        return (string) $value;
    }
}
