<?php

namespace App\Services;

// header looks like t=<unix time>,v1=<hex hmac-sha256 of "<t>.<raw body>">
class WebhookSignature
{
    public static function verify(string $header, string $payload, string $secret, int $tolerance, ?int $now = null): bool
    {
        if ($secret === '') {
            return false;
        }

        $parts = [];
        foreach (explode(',', $header) as $pair) {
            [$k, $v] = array_pad(explode('=', trim($pair), 2), 2, '');
            $parts[$k][] = $v;
        }

        $timestamp = $parts['t'][0] ?? '';
        if (! ctype_digit($timestamp) || abs(($now ?? time()) - (int) $timestamp) > $tolerance) {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$payload, $secret);

        foreach ($parts['v1'] ?? [] as $candidate) {
            if (hash_equals($expected, $candidate)) {
                return true;
            }
        }

        return false;
    }

    public static function sign(string $payload, string $secret, int $timestamp): string
    {
        return 't='.$timestamp.',v1='.hash_hmac('sha256', $timestamp.'.'.$payload, $secret);
    }
}
