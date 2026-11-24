<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Throwable;

// all money here is in nanodollars (1 USD = 1e9) so we never use floats
// pixelforge gives costs to 9 decimals so nothing gets rounded off
class Pricing
{
    public const NANOS_PER_USD = 1_000_000_000;

    // backup prices from the docs in case GET /v1/pricing is down
    private const FALLBACK = [
        'rates' => [
            'input_text_tokens' => '5.00',
            'input_image_tokens' => '10.00',
            'output_tokens' => '40.00',
            'reasoning_tokens' => '12.50',
        ],
        'tokens' => [
            'characters_per_text_token' => 4,
            'text_token_variance' => 0.15,
            'per_reference_image' => 765,
            'output' => [
                'low' => ['1024x1024' => 280, '1536x1024' => 420, '1024x1536' => 420],
                'medium' => ['1024x1024' => 1100, '1536x1024' => 1650, '1024x1536' => 1650],
                'high' => ['1024x1024' => 4300, '1536x1024' => 6450, '1024x1536' => 6450],
            ],
            'reasoning' => [
                'low' => ['min' => 0, 'max' => 500],
                'medium' => ['min' => 200, 'max' => 2000],
                'high' => ['min' => 1000, 'max' => 8000],
            ],
        ],
    ];

    public function __construct(private PixelforgeClient $client) {}

    // live price list from pixelforge, cached for an hour
    public function table(): array
    {
        return Cache::remember('pixelforge.pricing', 3600, function () {
            try {
                $p = $this->client->pricing();

                return ['rates' => $p['rates'], 'tokens' => $p['tokens']];
            } catch (Throwable) {
                return self::FALLBACK;
            }
        });
    }

    /**
     * the most this could cost, shown before they submit
     * uses +15% on the text tokens and the max reasoning tokens
     * so the real charge is never higher than this
     *
     * @return array{tokens: array<string,int>, provider_nanos: int, charge_nanos: int}
     */
    public function estimate(string $prompt, string $size, string $quality, int $referenceImages): array
    {
        $t = $this->table()['tokens'];
        $rates = $this->table()['rates'];

        $text = (int) ceil(mb_strlen($prompt) / $t['characters_per_text_token']);
        $textMax = (int) ceil($text * (1 + $t['text_token_variance']));

        $tokens = [
            'input_text_tokens' => $textMax,
            'input_image_tokens' => $referenceImages * $t['per_reference_image'],
            'output_tokens' => $t['output'][$quality][$size],
            'reasoning_tokens' => $t['reasoning'][$quality]['max'],
        ];

        $provider = $this->providerCost($tokens, $rates);

        return [
            'tokens' => $tokens,
            'provider_nanos' => $provider,
            'charge_nanos' => $this->charge($provider),
        ];
    }

    // pixelforge's cost = tokens * rate per 1M tokens, added up
    // rates come in as strings so we turn them into micro-dollars to keep it all integers
    public function providerCost(array $usage, array $rates): int
    {
        // tokens * rate / 1M tokens, in nanodollars, works out to tokens * rate_micro / 1000
        $numerator = 0;
        foreach (['input_text_tokens', 'input_image_tokens', 'output_tokens', 'reasoning_tokens'] as $key) {
            $numerator += (int) ($usage[$key] ?? 0) * self::toScaled($rates[$key], 6);
        }

        return intdiv($numerator + 999, 1000);
    }

    // what the user pays, our cost plus markup, rounded up to the nanodollar
    public function charge(int $providerNanos): int
    {
        $multiplier = 1_000_000 + self::toScaled((string) config('services.pixelforge.markup'), 6);

        return intdiv($providerNanos * $multiplier + 999_999, 1_000_000);
    }

    // "0.046125000" -> 46125000 nanodollars
    public static function usdToNanos(string $usd): int
    {
        return self::toScaled($usd, 9);
    }

    // nanodollars -> "0.046125000"
    public static function nanosToUsd(int $nanos): string
    {
        $sign = $nanos < 0 ? '-' : '';
        $nanos = abs($nanos);

        return $sign.intdiv($nanos, self::NANOS_PER_USD).'.'.str_pad((string) ($nanos % self::NANOS_PER_USD), 9, '0', STR_PAD_LEFT);
    }

    // turn a decimal string into an integer with $places decimals, no floats
    private static function toScaled(string $decimal, int $places): int
    {
        $decimal = trim($decimal);
        $negative = str_starts_with($decimal, '-');
        [$whole, $fraction] = array_pad(explode('.', ltrim($decimal, '-+'), 2), 2, '');
        $fraction = substr(str_pad($fraction, $places, '0'), 0, $places);
        $value = (int) $whole * (10 ** $places) + (int) $fraction;

        if ($negative) {
            return -$value;
        }

        return $value;
    }
}
