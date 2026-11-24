<?php

namespace App\Services;

use App\Models\CreditTransaction;
use App\Models\Generation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

// the only place a generation's status gets changed
// the submit job, webhooks and the sync command all go through here
// so it ends up right no matter which one shows up first or how many times
class GenerationUpdater
{
    public function __construct(private Pricing $pricing) {}

    // apply a generation from pixelforge (api response or webhook data.object)
    public function apply(Generation $generation, array $remote): Generation
    {
        return DB::transaction(function () use ($generation, $remote) {
            /** @var Generation $g */
            $g = Generation::whereKey($generation->id)->lockForUpdate()->firstOrFail();

            $g->pixelforge_id ??= $remote['id'] ?? null;
            $g->rates ??= $remote['rates'] ?? null;

            $status = $remote['status'] ?? null;

            if ($status && $g->canMoveTo($status)) {
                $g->status = $status;
                $g->started_at ??= self::time($remote['started_at'] ?? null);

                if ($status === Generation::SUCCEEDED) {
                    $this->succeed($g, $remote);
                } elseif ($status === Generation::FAILED) {
                    $this->fail($g, $remote['error']['code'] ?? 'generation_failed', $remote['error']['message'] ?? null, $remote['completed_at'] ?? null);
                }
            } elseif ($g->status === Generation::SUCCEEDED && ! empty($remote['image_url'])) {
                // newer signed url from a later fetch, same expiry so it's fine to swap in
                $g->image_url = $remote['image_url'];
                $g->image_url_expires_at = self::time($remote['image_url_expires_at'] ?? null);
            }

            $g->save();

            return $g;
        });
    }

    // mark it failed on our side, like when pixelforge rejects the submit. no charge
    public function markFailed(Generation $generation, string $code, ?string $message): Generation
    {
        return DB::transaction(function () use ($generation, $code, $message) {
            $g = Generation::whereKey($generation->id)->lockForUpdate()->firstOrFail();

            if (! $g->isFinal()) {
                $g->status = Generation::FAILED;
                $this->fail($g, $code, $message, null);
                $g->save();
            }

            return $g;
        });
    }

    private function succeed(Generation $g, array $remote): void
    {
        $usage = $remote['usage'] ?? [];
        $rates = $remote['rates'] ?? $g->rates;

        $provider = $this->pricing->providerCost($usage, $rates);
        $charge = $this->pricing->charge($provider);

        $g->usage = $usage;
        $g->rates = $rates;
        $g->provider_cost_nanos = $provider;
        $g->charge_nanos = $charge;
        $g->image_url = $remote['image_url'] ?? null;
        $g->image_url_expires_at = self::time($remote['image_url_expires_at'] ?? null);
        $g->completed_at = self::time($remote['completed_at'] ?? null) ?? now();

        $this->settle($g, $charge);
    }

    private function fail(Generation $g, string $code, ?string $message, ?string $completedAt): void
    {
        $g->error_code = $code;
        $g->error_message = $message;
        $g->provider_cost_nanos = 0;
        $g->charge_nanos = 0;
        $g->completed_at = self::time($completedAt) ?? now();

        $this->settle($g, 0);
    }

    // give back the hold and take the real charge
    // the unique key on (generation_id, type) means this can only happen once
    private function settle(Generation $g, int $charge): void
    {
        $rows = [[
            'user_id' => $g->user_id, 'generation_id' => $g->id,
            'type' => CreditTransaction::RELEASE, 'amount_nanos' => $g->estimated_charge_nanos,
            'created_at' => now(), 'updated_at' => now(),
        ]];

        if ($charge > 0) {
            $rows[] = [
                'user_id' => $g->user_id, 'generation_id' => $g->id,
                'type' => CreditTransaction::CHARGE, 'amount_nanos' => -$charge,
                'created_at' => now(), 'updated_at' => now(),
            ];
        }

        CreditTransaction::insertOrIgnore($rows);
    }

    private static function time(?string $value): ?Carbon
    {
        return $value ? Carbon::parse($value)->utc() : null;
    }
}
