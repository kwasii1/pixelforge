<?php

namespace App\Http\Controllers;

use App\Http\Requests\PurchaseCreditsRequest;
use App\Http\Resources\CreditTransactionResource;
use App\Services\Credits\CreditLedger;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CreditController extends Controller
{
    /**
     * The user's balance, the packs they can buy, and their credit activity.
     */
    public function index(Request $request): Response
    {
        $transactions = $request->user()->creditTransactions()
            ->with('generation:id,prompt')
            ->latest()
            ->orderByDesc('id')
            ->paginate(20);

        return Inertia::render('credits/Index', [
            'packs' => PurchaseCreditsRequest::PACKS,
            'transactions' => CreditTransactionResource::collection($transactions),
        ]);
    }

    /**
     * A fake purchase: no payment is taken, the credits are simply added.
     */
    public function store(PurchaseCreditsRequest $request, CreditLedger $ledger): RedirectResponse
    {
        $ledger->purchase($request->user(), $request->nanodollars());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => sprintf('Added %s in credits.', Money::format($request->nanodollars(), 2)),
        ]);

        return back();
    }
}
