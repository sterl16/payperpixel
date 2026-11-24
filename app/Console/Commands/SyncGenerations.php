<?php

namespace App\Console\Commands;

use App\Models\Generation;
use App\Services\GenerationUpdater;
use App\Services\PixelforgeClient;
use App\Services\PixelforgeException;
use Illuminate\Console\Command;

// webhooks aren't guaranteed so anything still running after a minute
// gets checked with GET /v1/generations/{id} and goes through the same updater
class SyncGenerations extends Command
{
    protected $signature = 'generations:sync {--older-than=1 : minutes since last update}';

    protected $description = 'Poll Pixelforge for generations whose webhooks have not arrived';

    public function handle(PixelforgeClient $client, GenerationUpdater $updater): int
    {
        Generation::whereIn('status', [Generation::QUEUED, Generation::PROCESSING])
            ->whereNotNull('pixelforge_id')
            ->where('updated_at', '<', now()->subMinutes((int) $this->option('older-than')))
            ->orderBy('id')
            ->limit(50)
            ->each(function (Generation $g) use ($client, $updater) {
                try {
                    $updater->apply($g, $client->getGeneration($g->pixelforge_id));
                    $g->touch();
                } catch (PixelforgeException $e) {
                    $this->warn("{$g->id}: {$e->getMessage()}");
                }
            });

        return self::SUCCESS;
    }
}
