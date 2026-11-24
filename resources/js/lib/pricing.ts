// same math as app/Services/Pricing.php so the estimate matches what the server holds
// uses BigInt nanodollars so there's no floating point

export interface PricingTable {
    rates: Record<'input_text_tokens' | 'input_image_tokens' | 'output_tokens' | 'reasoning_tokens', string>;
    tokens: {
        characters_per_text_token: number;
        text_token_variance: number;
        per_reference_image: number;
        output: Record<string, Record<string, number>>;
        reasoning: Record<string, { min: number; max: number }>;
    };
}

const NANOS = 1_000_000_000n;

function toScaled(decimal: string, places: number): bigint {
    const negative = decimal.trim().startsWith('-');
    const [whole, fraction = ''] = decimal.trim().replace(/^[-+]/, '').split('.');
    const value = BigInt(whole || '0') * 10n ** BigInt(places) + BigInt(fraction.padEnd(places, '0').slice(0, places) || '0');
    if (negative) {
        return -value;
    }
    return value;
}

function ceilDiv(a: bigint, b: bigint): bigint {
    return (a + b - 1n) / b;
}

export function formatUsd(nanos: bigint, decimals = 4): string {
    // round up so we never show less than the real amount
    const step = 10n ** BigInt(9 - decimals);
    const rounded = ceilDiv(nanos, step);
    const whole = rounded / 10n ** BigInt(decimals);
    const fraction = (rounded % 10n ** BigInt(decimals)).toString().padStart(decimals, '0');
    return `$${whole}.${fraction}`;
}

export function estimate(table: PricingTable, markup: string, prompt: string, size: string, quality: string, referenceImages: number) {
    const t = table.tokens;
    // count real characters, not UTF-16 units
    const chars = [...prompt].length;
    const text = Math.ceil(chars / t.characters_per_text_token);
    const tokens = {
        input_text_tokens: Math.ceil(text * (1 + t.text_token_variance)),
        input_image_tokens: referenceImages * t.per_reference_image,
        output_tokens: t.output[quality]?.[size] ?? 0,
        reasoning_tokens: t.reasoning[quality]?.max ?? 0,
    };

    let numerator = 0n;
    for (const key of Object.keys(tokens) as (keyof typeof tokens)[]) {
        numerator += BigInt(tokens[key]) * toScaled(table.rates[key], 6);
    }
    const provider = ceilDiv(numerator, 1000n);
    const charge = ceilDiv(provider * (1_000_000n + toScaled(markup, 6)), 1_000_000n);

    return { tokens, provider, charge };
}

// exact amount from the server, all 9 decimals, e.g. "0.080478750" -> "$0.080478750"
export function exactUsd(usd: string): string {
    if (usd.startsWith('-')) {
        return '-$' + usd.slice(1);
    }
    return '$' + usd;
}

export function usdToNanos(usd: string): bigint {
    return toScaled(usd, 9);
}

export { NANOS };
