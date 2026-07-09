<?php

namespace App\Support;

class PhoneNumber
{
    /** Normalize to international digits (e.g. 923001234567). */
    public static function normalize(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if ($digits === '') {
            return '';
        }

        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }

        if (str_starts_with($digits, '0') && strlen($digits) === 11) {
            $digits = '92'.substr($digits, 1);
        }

        if (! str_starts_with($digits, '92') && strlen($digits) === 10) {
            $digits = '92'.$digits;
        }

        return $digits;
    }

    /** E.164-style prefix for WhatsApp APIs (e.g. +923001234567). */
    public static function toInternational(string $phone): string
    {
        $digits = self::normalize($phone);

        return $digits !== '' ? '+'.$digits : '';
    }

    public static function mask(string $phone): string
    {
        $digits = self::normalize($phone);
        if (strlen($digits) < 4) {
            return '****';
        }

        return str_repeat('*', max(strlen($digits) - 4, 4)).substr($digits, -4);
    }
}
