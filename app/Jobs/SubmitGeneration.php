<?php

namespace App\Jobs;

use App\Models\Generation;
use App\Services\GenerationUpdater;
use App\Services\PixelforgeClient;
use App\Services\PixelforgeException;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class SubmitGeneration implements ShouldQueue
{
    use Queueable;

    public int $tries = 6;

    public function __construct(public Generation $generation) {}

    public function handle(PixelforgeClient $client, GenerationUpdater $updater): void
    {
        $g = $this->generation->refresh();

        if ($g->status !== Generation::PENDING) {
            // already accepted, a webhook can get here before we do
            return;
        }

        $g->increment('submit_attempts');

        $params = array_filter([
            'prompt' => $g->prompt,
            'size' => $g->size,
            'quality' => $g->quality,
            'reference_images' => $g->reference_images ?: null,
            'metadata' => ['generation_id' => (string) $g->id],
        ]);

        try {
            // same Idempotency-Key every try so a retry never makes a second one
            $remote = $client->createGeneration($params, $g->idempotency_key);
        } catch (PixelforgeException $e) {
            if ($e->isRetryable() && $this->attempts() < $this->tries) {
                $this->release($e->retryAfter ?? min(60, 2 ** $this->attempts()));

                return;
            }

            $updater->markFailed($g, $e->errorCode ?? 'submit_failed', $e->getMessage());

            return;
        }

        $updater->apply($g, $remote);
    }

    public function failed(?Throwable $e): void
    {
        app(GenerationUpdater::class)->markFailed($this->generation, 'submit_failed', $e?->getMessage());
    }
}
