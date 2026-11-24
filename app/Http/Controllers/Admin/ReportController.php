<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Generation;
use App\Services\PixelforgeClient;
use App\Services\Pricing;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

// spend, provider cost and profit per day and per user
// grouped by the UTC day it completed since that's how pixelforge bills
// so each day's provider cost should match GET /v1/usage exactly
class ReportController extends Controller
{
    public function __invoke(Request $request, PixelforgeClient $client): Response
    {
        $end = Carbon::parse($request->query('end', now('UTC')->toDateString()), 'UTC');
        $start = Carbon::parse($request->query('start', $end->copy()->subDays(13)->toDateString()), 'UTC');
        if ($start->diffInDays($end) > 91) {
            $start = $end->copy()->subDays(91);
        }

        $base = Generation::query()
            ->where('status', Generation::SUCCEEDED)
            ->whereBetween('completed_at', [$start->copy()->startOfDay(), $end->copy()->endOfDay()]);

        $day = DB::connection()->getDriverName() === 'sqlite' ? 'date(completed_at)' : 'DATE(completed_at)';

        $byDay = (clone $base)
            ->selectRaw("$day as date, count(*) as generations, sum(charge_nanos) as charged, sum(provider_cost_nanos) as cost")
            ->groupByRaw($day)->get()->keyBy('date');

        $byUser = (clone $base)
            ->join('users', 'users.id', '=', 'generations.user_id')
            ->selectRaw('users.id, users.name, users.email, count(*) as generations, sum(charge_nanos) as charged, sum(provider_cost_nanos) as cost')
            ->groupBy('users.id', 'users.name', 'users.email')
            ->orderByDesc('charged')->get();

        $usage = $this->usage($client, $start, $end);

        $days = [];
        for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
            $date = $d->toDateString();
            $row = $byDay->get($date);
            $days[] = $this->row([
                'date' => $date,
                'generations' => (int) ($row->generations ?? 0),
            ], (int) ($row->charged ?? 0), (int) ($row->cost ?? 0)) + [
                'pixelforge_cost' => $usage[$date] ?? null,
            ];
        }

        return Inertia::render('Admin/Report', [
            'start' => $start->toDateString(),
            'end' => $end->toDateString(),
            'days' => array_reverse($days),
            'users' => $byUser->map(fn ($u) => $this->row([
                'name' => $u->name,
                'email' => $u->email,
                'generations' => (int) $u->generations,
            ], (int) $u->charged, (int) $u->cost)),
            'totals' => $this->row([
                'generations' => array_sum(array_column($days, 'generations')),
            ], (int) $byDay->sum('charged'), (int) $byDay->sum('cost')),
            'usage_available' => $usage !== [],
        ]);
    }

    private function row(array $fields, int $charged, int $cost): array
    {
        return $fields + [
            'charged' => Pricing::nanosToUsd($charged),
            'cost' => Pricing::nanosToUsd($cost),
            'profit' => Pricing::nanosToUsd($charged - $cost),
        ];
    }

    // date => cost_usd from pixelforge's usage report
    private function usage(PixelforgeClient $client, Carbon $start, Carbon $end): array
    {
        try {
            $data = $client->usage($start->toDateString(), $end->toDateString())['data'] ?? [];
        } catch (Throwable) {
            return [];
        }

        return collect($data)->mapWithKeys(fn ($d) => [$d['date'] => $d['cost_usd']])->all();
    }
}
