<?php

namespace Tests\Feature;

use App\Models\CreditTransaction;
use App\Models\Generation;
use App\Models\User;
use App\Models\WebhookEvent;
use App\Services\Pricing;
use App\Services\WebhookSignature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class GenerationFlowTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'whsec_test';

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.pixelforge.webhook_secret' => self::SECRET, 'services.pixelforge.markup' => '0.25']);
        Http::preventStrayRequests();
    }

    public function test_provider_cost_matches_the_documented_usage_example(): void
    {
        $p = app(Pricing::class);
        $cost = $p->providerCost(
            ['input_text_tokens' => 41, 'input_image_tokens' => 0, 'output_tokens' => 3300, 'reasoning_tokens' => 2950],
            $this->rates(),
        );

        $this->assertSame('0.169080000', Pricing::nanosToUsd($cost));
        $this->assertSame('0.211350000', Pricing::nanosToUsd($p->charge($cost)));
    }

    public function test_rejects_bad_signatures_and_stale_timestamps(): void
    {
        $g = $this->makeGeneration(User::factory()->create());

        $this->sendWebhook('evt_1', $this->remote($g, 'processing'), secret: 'wrong')->assertStatus(400);
        $this->sendWebhook('evt_1', $this->remote($g, 'processing'), t: time() - 301)->assertStatus(400);

        $this->assertSame(0, WebhookEvent::count());
        $this->assertSame('queued', $g->fresh()->status);
    }

    public function test_success_delivered_twice_is_recorded_once(): void
    {
        $user = $this->userWithCredits(1_000_000_000);
        $g = $this->makeGeneration($user);

        $this->sendWebhook('evt_s', $this->succeeded($g))->assertOk();
        $this->sendWebhook('evt_s', $this->succeeded($g))->assertOk();

        $g->refresh();
        $this->assertSame('succeeded', $g->status);
        $this->assertSame(169_080_000, $g->provider_cost_nanos);
        $this->assertSame(211_350_000, $g->charge_nanos);
        $this->assertSame(1, WebhookEvent::count());
        $this->assertSame(1, CreditTransaction::where('type', 'charge')->count());
        $this->assertSame(1_000_000_000 - 211_350_000, $user->balanceNanos());
    }

    public function test_same_outcome_under_a_different_event_id_is_not_charged_twice(): void
    {
        $user = $this->userWithCredits(1_000_000_000);
        $g = $this->makeGeneration($user);

        $this->sendWebhook('evt_a', $this->succeeded($g))->assertOk();
        $this->sendWebhook('evt_b', $this->succeeded($g))->assertOk();

        $this->assertSame(1, CreditTransaction::where('type', 'charge')->count());
        $this->assertSame(1_000_000_000 - 211_350_000, $user->balanceNanos());
    }

    public function test_late_processing_event_never_moves_a_finished_generation_back(): void
    {
        $g = $this->makeGeneration(User::factory()->create());

        $this->sendWebhook('evt_s', $this->succeeded($g))->assertOk();
        $this->sendWebhook('evt_p', $this->remote($g, 'processing'))->assertOk();

        $this->assertSame('succeeded', $g->fresh()->status);
    }

    public function test_failed_generation_costs_nothing_and_releases_the_hold(): void
    {
        $user = $this->userWithCredits(1_000_000_000);
        $g = $this->makeGeneration($user);

        $this->sendWebhook('evt_f', $this->remote($g, 'failed', [
            'error' => ['code' => 'generation_failed', 'message' => 'nope'],
            'completed_at' => '2026-11-24T10:00:20Z',
        ]))->assertOk();

        $this->assertSame(0, $g->fresh()->charge_nanos);
        $this->assertSame(1_000_000_000, $user->balanceNanos());
    }

    public function test_webhook_that_beats_the_create_response_is_matched_by_metadata(): void
    {
        $g = $this->makeGeneration(User::factory()->create());
        $g->update(['status' => Generation::PENDING]);

        $this->sendWebhook('evt_p', $this->remote($g, 'processing'))->assertOk();

        $g->refresh();
        $this->assertSame('processing', $g->status);
        $this->assertSame('gen_abc', $g->pixelforge_id);
    }

    public function test_submit_holds_the_estimate_and_blocks_users_who_cannot_afford_it(): void
    {
        Http::fake([
            '*/v1/pricing' => Http::response([], 500),
            '*/v1/generations' => Http::response(['id' => 'gen_new', 'object' => 'generation', 'status' => 'queued'], 202),
        ]);
        $user = User::factory()->create();
        $form = ['prompt' => 'a cat', 'size' => '1024x1024', 'quality' => 'medium'];

        $this->actingAs($user)->post('/generations', $form)->assertSessionHasErrors('prompt');
        $this->assertSame(0, Generation::count());

        $this->post('/credits', ['amount' => 5])->assertRedirect();
        $this->post('/generations', $form)->assertRedirect('/generations');

        $g = Generation::sole();
        $this->assertSame('queued', $g->status);
        $this->assertSame('gen_new', $g->pixelforge_id);
        $this->assertSame(5_000_000_000 - $g->estimated_charge_nanos, $user->balanceNanos());

        Http::assertSent(fn ($r) => $r->url() !== '' && str_ends_with($r->url(), '/generations')
            && $r->hasHeader('Idempotency-Key', $g->idempotency_key)
            && $r['metadata']['generation_id'] === (string) $g->id);
    }

    public function test_content_policy_rejection_fails_for_free(): void
    {
        Http::fake([
            '*/v1/pricing' => Http::response([], 500),
            '*/v1/generations' => Http::response(['error' => ['type' => 'invalid_request_error', 'code' => 'content_policy_violation', 'message' => 'Prompt rejected']], 400),
        ]);
        $user = $this->userWithCredits(5_000_000_000);

        $this->actingAs($user)->post('/generations', ['prompt' => 'x [blocked]', 'size' => '1024x1024', 'quality' => 'low']);

        $g = Generation::sole();
        $this->assertSame('failed', $g->status);
        $this->assertSame('content_policy_violation', $g->error_code);
        $this->assertSame(5_000_000_000, $user->balanceNanos());
    }

    public function test_admin_report_is_admin_only(): void
    {
        $this->actingAs(User::factory()->create())->get('/admin/report')->assertForbidden();
    }

    private function rates(): array
    {
        return ['input_text_tokens' => '5.00', 'input_image_tokens' => '10.00', 'output_tokens' => '40.00', 'reasoning_tokens' => '12.50'];
    }

    private function userWithCredits(int $nanos): User
    {
        $user = User::factory()->create();
        $user->creditTransactions()->create(['type' => 'purchase', 'amount_nanos' => $nanos]);

        return $user;
    }

    private function makeGeneration(User $user, int $estimate = 500_000_000): Generation
    {
        $g = $user->generations()->create([
            'idempotency_key' => (string) Str::uuid(), 'prompt' => 'a cat', 'size' => '1024x1024',
            'quality' => 'medium', 'status' => Generation::QUEUED, 'estimated_charge_nanos' => $estimate,
        ]);
        $user->creditTransactions()->create(['generation_id' => $g->id, 'type' => 'hold', 'amount_nanos' => -$estimate]);

        return $g;
    }

    private function remote(Generation $g, string $status, array $extra = []): array
    {
        return array_merge([
            'id' => 'gen_abc', 'object' => 'generation', 'status' => $status,
            'metadata' => ['generation_id' => (string) $g->id],
            'rates' => $this->rates(),
            'usage' => null, 'image_url' => null, 'image_url_expires_at' => null, 'error' => null,
            'created_at' => '2026-11-24T10:00:00Z', 'started_at' => '2026-11-24T10:00:02Z', 'completed_at' => null,
        ], $extra);
    }

    private function succeeded(Generation $g): array
    {
        return $this->remote($g, 'succeeded', [
            'usage' => ['input_text_tokens' => 41, 'input_image_tokens' => 0, 'output_tokens' => 3300, 'reasoning_tokens' => 2950, 'total_tokens' => 6291],
            'image_url' => 'https://img.example/x.png',
            'image_url_expires_at' => '2026-11-25T10:00:20Z',
            'completed_at' => '2026-11-24T10:00:20Z',
        ]);
    }

    private function sendWebhook(string $eventId, array $object, ?int $t = null, string $secret = self::SECRET): TestResponse
    {
        $body = json_encode([
            'id' => $eventId, 'object' => 'event', 'type' => 'generation.'.$object['status'],
            'created_at' => now()->toIso8601String(), 'data' => ['object' => $object],
        ]);

        return $this->call('POST', '/webhooks/pixelforge', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_PIXELFORGE_SIGNATURE' => WebhookSignature::sign($body, $secret, $t ?? time()),
        ], $body);
    }
}
