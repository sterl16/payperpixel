<?php

namespace App\Http\Controllers;

use App\Jobs\SubmitGeneration;
use App\Models\CreditTransaction;
use App\Models\Generation;
use App\Models\User;
use App\Services\Pricing;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class GenerationController extends Controller
{
    public const SIZES = ['1024x1024', '1536x1024', '1024x1536'];
    public const QUALITIES = ['low', 'medium', 'high'];

    public function create(Request $request, Pricing $pricing): Response
    {
        return Inertia::render('Generate', [
            'pricing' => $pricing->table(),
            'markup' => (string) config('services.pixelforge.markup'),
            'sizes' => self::SIZES,
            'qualities' => self::QUALITIES,
            'balance' => Pricing::nanosToUsd($request->user()->balanceNanos()),
        ]);
    }

    public function store(Request $request, Pricing $pricing): RedirectResponse
    {
        $data = $request->validate([
            'prompt' => ['required', 'string', 'max:4000'],
            'size' => ['required', Rule::in(self::SIZES)],
            'quality' => ['required', Rule::in(self::QUALITIES)],
            'reference_images' => ['nullable', 'array', 'max:4'],
            'reference_images.*' => ['required', 'url:http,https', 'max:2048'],
        ]);

        $refs = array_values($data['reference_images'] ?? []);
        $estimate = $pricing->estimate($data['prompt'], $data['size'], $data['quality'], count($refs));

        $generation = DB::transaction(function () use ($request, $data, $refs, $estimate) {
            // lock the user so two submits at once can't spend the same credits
            $user = User::whereKey($request->user()->id)->lockForUpdate()->first();

            if ($user->balanceNanos() < $estimate['charge_nanos']) {
                throw ValidationException::withMessages([
                    'prompt' => 'Not enough credits. This generation needs up to $'
                        .Pricing::nanosToUsd($estimate['charge_nanos']).'.',
                ]);
            }

            $generation = $user->generations()->create([
                'idempotency_key' => (string) Str::uuid(),
                'prompt' => $data['prompt'],
                'size' => $data['size'],
                'quality' => $data['quality'],
                'reference_images' => $refs,
                'status' => Generation::PENDING,
                'estimated_charge_nanos' => $estimate['charge_nanos'],
            ]);

            $user->creditTransactions()->create([
                'generation_id' => $generation->id,
                'type' => CreditTransaction::HOLD,
                'amount_nanos' => -$estimate['charge_nanos'],
            ]);

            return $generation;
        });

        SubmitGeneration::dispatch($generation)->afterCommit();

        return to_route('generations.index');
    }

    public function index(Request $request): Response
    {
        $generations = $request->user()->generations()->latest('id')->paginate(20)
            ->through(fn (Generation $g) => [
                'id' => $g->id,
                'prompt' => $g->prompt,
                'size' => $g->size,
                'quality' => $g->quality,
                'reference_images' => $g->reference_images ?? [],
                'status' => $g->status,
                'estimated_charge' => Pricing::nanosToUsd($g->estimated_charge_nanos),
                'charge' => $g->charge_nanos === null ? null : Pricing::nanosToUsd($g->charge_nanos),
                'image_url' => $g->image_url,
                'image_url_expires_at' => $g->image_url_expires_at?->toIso8601String(),
                'error' => $g->error_message ?? $g->error_code,
                'created_at' => $g->created_at->toIso8601String(),
                'completed_at' => $g->completed_at?->toIso8601String(),
            ]);

        return Inertia::render('Generations', [
            'generations' => $generations,
            'balance' => Pricing::nanosToUsd($request->user()->balanceNanos()),
        ]);
    }
}
