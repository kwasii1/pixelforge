<?php

namespace App\Services\Credits;

use App\Enums\CreditTransactionType;
use App\Models\CreditTransaction;
use App\Models\Generation;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * The user's credit balance as an append-only ledger. All amounts are nanodollars.
 *
 * Submitting a generation holds the most it can cost. When it succeeds the
 * hold is released and the actual price charged; when it fails the hold is
 * released. Each of hold, release and charge is unique per generation in the
 * database, so a generation can never be charged twice. users.balance is kept
 * equal to the ledger's sum and updated under a row lock.
 */
class CreditLedger
{
    public function purchase(User $user, int $amount): CreditTransaction
    {
        return DB::transaction(fn (): CreditTransaction => $this->record($user, CreditTransactionType::Purchase, $amount));
    }

    /**
     * Reserve the most this generation can cost. Returns false, recording nothing, if the user can't afford it.
     *
     * Call inside a transaction, before the generation is visible to anything else.
     */
    public function hold(User $user, Generation $generation): bool
    {
        $lockedUser = $this->lockAccount($user);

        if ($lockedUser->balance < $generation->estimated_max_price) {
            return false;
        }

        $this->record($lockedUser, CreditTransactionType::Hold, -$generation->estimated_max_price, $generation);
        $user->balance = $lockedUser->balance;

        return true;
    }

    /**
     * Swap the hold for the actual price. Call inside the transaction that records the success.
     */
    public function settle(Generation $generation): void
    {
        $this->release($generation);

        if (! $this->hasEntry($generation, CreditTransactionType::Charge)) {
            $this->record($generation->user, CreditTransactionType::Charge, -(int) $generation->price, $generation);
        }
    }

    /**
     * Give back the hold, if there is one that hasn't been given back. Call inside the transaction that records the failure.
     */
    public function release(Generation $generation): void
    {
        $hold = $generation->creditTransactions()->where('type', CreditTransactionType::Hold)->first();

        if ($hold !== null && ! $this->hasEntry($generation, CreditTransactionType::Release)) {
            $this->record($generation->user, CreditTransactionType::Release, -$hold->amount, $generation);
        }
    }

    private function record(User $user, CreditTransactionType $type, int $amount, ?Generation $generation = null): CreditTransaction
    {
        $lockedUser = $this->lockAccount($user);

        $transaction = $lockedUser->creditTransactions()->create([
            'generation_id' => $generation?->id,
            'type' => $type,
            'amount' => $amount,
        ]);

        $lockedUser->increment('balance', $amount);
        $user->balance = $lockedUser->balance;

        return $transaction;
    }

    private function hasEntry(Generation $generation, CreditTransactionType $type): bool
    {
        return $generation->creditTransactions()->where('type', $type)->exists();
    }

    /**
     * Lock the user's balance row until the surrounding transaction ends, and refresh the given model's balance.
     */
    public function lockAccount(User $user): User
    {
        $lockedUser = User::query()->whereKey($user->getKey())->lockForUpdate()->firstOrFail();
        $user->balance = $lockedUser->balance;

        return $lockedUser;
    }
}
