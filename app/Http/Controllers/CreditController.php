<?php

namespace App\Http\Controllers;

use App\Models\CreditTransaction;
use App\Services\Pricing;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CreditController extends Controller
{
    public const PACKS = [5, 10, 25];

    // fake purchase, no payment, just adds credits
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['amount' => ['required', 'integer', Rule::in(self::PACKS)]]);

        $request->user()->creditTransactions()->create([
            'type' => CreditTransaction::PURCHASE,
            'amount_nanos' => $data['amount'] * Pricing::NANOS_PER_USD,
        ]);

        return back()->with('success', '$'.$data['amount'].' in credits added.');
    }
}
