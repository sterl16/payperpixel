<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class PixelforgeClient
{
    private function http(): PendingRequest
    {
        return Http::baseUrl(config('services.pixelforge.base_url'))
            ->withToken(config('services.pixelforge.api_key'))
            ->acceptJson()
            ->timeout(15);
    }

    public function createGeneration(array $params, string $idempotencyKey): array
    {
        return $this->send(fn () => $this->http()
            ->withHeaders(['Idempotency-Key' => $idempotencyKey])
            ->post('/generations', $params));
    }

    public function getGeneration(string $id): array
    {
        return $this->send(fn () => $this->http()->get('/generations/'.$id));
    }

    public function usage(string $startDate, string $endDate): array
    {
        return $this->send(fn () => $this->http()->get('/usage', [
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]));
    }

    public function pricing(): array
    {
        return $this->send(fn () => Http::baseUrl(config('services.pixelforge.base_url'))
            ->acceptJson()->timeout(10)->get('/pricing'));
    }

    private function send(callable $request): array
    {
        try {
            /** @var Response $response */
            $response = $request();
        } catch (ConnectionException $e) {
            throw new PixelforgeException(0, 'connection_error', $e->getMessage());
        }

        if ($response->successful()) {
            return $response->json();
        }

        $retryAfter = $response->header('Retry-After');

        throw new PixelforgeException(
            $response->status(),
            $response->json('error.code'),
            $response->json('error.message') ?? 'Pixelforge request failed',
            $retryAfter !== '' ? (int) $retryAfter : null,
        );
    }
}
