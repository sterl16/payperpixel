<?php

namespace App\Http\Controllers;

use App\Models\Generation;
use App\Models\WebhookEvent;
use App\Services\GenerationUpdater;
use App\Services\WebhookSignature;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class WebhookController extends Controller
{
    public function __invoke(Request $request, GenerationUpdater $updater): Response
    {
        // need the raw body for the signature, before any json decoding
        $payload = $request->getContent();

        $valid = WebhookSignature::verify(
            (string) $request->header('Pixelforge-Signature'),
            $payload,
            (string) config('services.pixelforge.webhook_secret'),
            (int) config('services.pixelforge.webhook_tolerance'),
        );

        if (! $valid) {
            return response('Invalid signature', 400);
        }

        $event = json_decode($payload, true);
        if (! is_array($event) || empty($event['id'])) {
            return response('Malformed event', 400);
        }

        $remote = $event['data']['object'] ?? null;

        // save the event and apply it together
        // if applying fails the event rolls back too so pixelforge's retry still works
        DB::transaction(function () use ($event, $remote, $payload, $updater) {
            $inserted = WebhookEvent::insertOrIgnore([
                'event_id' => $event['id'],
                'type' => $event['type'] ?? 'unknown',
                'pixelforge_generation_id' => $remote['id'] ?? null,
                'payload' => $payload,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if ($inserted === 0) {
                // already got this one
                return;
            }

            if (is_array($remote) && ($remote['object'] ?? null) === 'generation') {
                $generation = $this->findGeneration($remote);

                if ($generation) {
                    $updater->apply($generation, $remote);
                }
            }

            WebhookEvent::where('event_id', $event['id'])->update(['processed_at' => now()]);
        });

        return response('ok', 200);
    }

    // the webhook can show up before our POST comes back so check our own id in metadata first
    private function findGeneration(array $remote): ?Generation
    {
        $localId = $remote['metadata']['generation_id'] ?? null;

        if ($localId !== null) {
            $g = Generation::find($localId);
            if ($g && ($g->pixelforge_id === null || $g->pixelforge_id === $remote['id'])) {
                return $g;
            }
        }

        return Generation::where('pixelforge_id', $remote['id'] ?? '')->first();
    }
}
