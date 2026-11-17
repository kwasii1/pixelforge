# Pixelforge Studio

Generate images through the [Pixelforge](https://pixelforge.promptedit.dev/docs) API, see the price before you submit, and pay exactly what each image cost plus 25%. Failed generations are free.

Laravel 13, Inertia v3, Vue 3, Tailwind v4 and MySQL.

## What it does

- **Generate**: write a prompt, choose a size, a quality and up to four reference image URLs. The page shows the price range live and the most you can pay.
- **History**: every generation with its live status (waiting for capacity, queued, processing, succeeded, failed), its estimated and actual cost, the image, and a breakdown of tokens × rate.
- **Credits**: a balance in US dollars. Buy $5, $10 or $25 packs (a demo, no payment is taken). A generation is blocked if the balance can't cover the most it could cost.
- **Operator report** (`/admin/report`): credit spend, provider cost and profit per UTC day and per user, with each day checked against Pixelforge's `GET /v1/usage`.

## Running it locally

Requirements: PHP 8.4, Composer, Node 22, MySQL 8.

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
```

Create two databases, `pixelforge` and `pixelforge_testing`, then set your MySQL credentials and Pixelforge key in `.env`:

```dotenv
PIXELFORGE_API_KEY=pk_...
PIXELFORGE_WEBHOOK_SECRET=whsec_...
```

```bash
php artisan migrate
composer run dev   # web server, queue worker, scheduler, logs and Vite
```

The scheduler is registered with `DevCommands` in `AppServiceProvider`. It runs `pixelforge:sync-generations` every minute, which polls Pixelforge for missed webhooks.

To receive webhooks locally, expose the app with a tunnel (for example `ngrok http 8000`) and set `https://<tunnel>/webhooks/pixelforge` as the webhook URL of your **local** Pixelforge API key. Without a tunnel, the scheduler still picks up results by polling, about two minutes after a generation goes quiet.

Make yourself an operator to see the report. A **Report** link then appears in the header, and the page is at `/admin/report`:

```bash
php artisan app:make-admin you@example.com
```

Tests run against the `pixelforge_testing` MySQL database:

```bash
php artisan test
```

## How money works

**All money is an integer number of nanodollars** (1 USD = 1,000,000,000). Pixelforge reports exact amounts with nine decimal places and rates such as `"12.50"` per million tokens. That makes every token cost a whole number of nanodollars, so costs are exact and never touch a float. Display values are rounded only at the last moment, and the exact value is always available on hover.

**Provider cost** = Σ tokens × rate per million ÷ 1,000,000, worked out from the `usage` and the `rates` returned with each generation. A generation is billed at the rates it was created with, not today's.

**User price** = provider cost × 5/4, rounded up to the nanodollar. At Pixelforge's current rates the result is always exact. Rounding only happens if rates ever become finer than that, and it never undercharges.

**The estimate** is a range, because two token counts are only known once the image finishes:

| Tokens           | Before submitting                                           |
| ---------------- | ----------------------------------------------------------- |
| Prompt text      | `ceil(characters / 4)` ±15% (Unicode characters, not bytes) |
| Reference images | exactly 765 each                                            |
| Image output     | exact, from the size and quality table                      |
| Reasoning        | min–max range by quality                                    |

Both ends are priced with the same formula as the actual cost. The browser runs a line-for-line copy of `PricingCalculator::estimate` for the live preview, and the server recomputes and stores its own estimate on submit. The actual charge can never exceed the top of the range.

**Credits** are an append-only ledger (`credit_transactions`):

1. Submitting a generation **holds** its maximum estimate. The user row is locked first, so two submissions at once can't spend the same credits.
2. When it **succeeds**, the hold is **released** and the actual price is **charged**.
3. When it **fails**, for any reason, the hold is released. The net cost is $0.

`hold`, `release` and `charge` are unique per generation in the database, and `users.balance` always equals the sum of the ledger.

## Coping with Pixelforge

| Pixelforge does this                                         | The app does this                                                                                                                                                                                     |
| ------------------------------------------------------------ | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| 429 rate or concurrency limit, 503 overloaded, network error | The `SubmitGeneration` job waits for `Retry-After` and backs off further each time, for up to 10 minutes. Meanwhile the UI shows "Waiting for capacity". After that it fails the generation for free. |
| Retried requests                                             | The generation's UUID is the `Idempotency-Key` on every attempt, so Pixelforge creates and bills at most one generation for it.                                                                       |
| 400 content policy, 429 daily limit                          | Fails straight away, free, with a plain-language reason.                                                                                                                                              |
| Accepts a generation, then it fails                          | Recorded as failed with $0 charged. "Try again" refills the form.                                                                                                                                     |
| Duplicate webhooks                                           | Event ids are unique in `webhook_events`. A redelivery gets a 200 and is not processed again.                                                                                                         |
| Out-of-order webhooks                                        | Events are applied by the generation's status, which only moves forward (`queued → processing → succeeded \| failed`). Succeeded and failed are final.                                                |
| The same success reported by two different events            | The cost is recorded only on the single transition into `succeeded`, and the ledger's unique `charge` prevents a second charge.                                                                       |
| A webhook arrives before the create request returns          | Matched by our UUID in `metadata.generation_id`.                                                                                                                                                      |
| A webhook never arrives                                      | `pixelforge:sync-generations` runs every minute and polls generations with no news for two minutes. It also re-queues submissions that were lost.                                                     |
| A forged or replayed webhook                                 | HMAC-SHA256 signature over `"t.raw_body"`, compared in constant time, and rejected if older than five minutes.                                                                                        |
| A request timed out but actually went through                | If we had already failed it locally, Pixelforge's later report wins. The cost we record then matches what Pixelforge billed.                                                                          |

All status and money changes go through one class, `GenerationRecorder`. It locks the generation row, so concurrent webhooks and polls are applied one at a time.

## Decisions worth knowing

- **Reports use the UTC completion day**, because that's how `/v1/usage` bills. A generation created at 23:59 that finishes at 00:00:10 counts on the next day.
- **Images aren't stored.** Pixelforge's signed URLs expire after 24 hours, and then the card shows "Image expired".
- **No automatic retry after a provider failure.** A failed generation is free and final. Retrying is the user's choice, and gets a fresh estimate.
- **Pricing is cached for an hour**, with the last known table kept as a fallback, so estimates still work if `/v1/pricing` is down. Actual costs never depend on it.
- **One Pixelforge API key per environment.** Each has its own webhook secret and its own `/v1/usage`, so the deployed app reconciles exactly with its own key.

## Code map

| Concern                           | Where                                                                                                             |
| --------------------------------- | ----------------------------------------------------------------------------------------------------------------- |
| Money helpers                     | `app/Support/Money.php`                                                                                           |
| Cost and estimate formulas        | `app/Services/Pricing/PricingCalculator.php` (browser copy: `resources/js/composables/useCostEstimate.ts`)        |
| Pixelforge API client and errors  | `app/Services/Pixelforge/`                                                                                        |
| Status changes and cost recording | `app/Services/Generations/GenerationRecorder.php`                                                                 |
| Credit ledger                     | `app/Services/Credits/CreditLedger.php`                                                                           |
| Submission with retries           | `app/Jobs/SubmitGeneration.php`                                                                                   |
| Webhooks                          | `routes/webhooks.php`, `app/Http/Middleware/VerifyPixelforgeSignature.php`, `app/Jobs/ProcessPixelforgeEvent.php` |
| Polling fallback                  | `app/Console/Commands/SyncPixelforgeGenerations.php`                                                              |
| Operator report                   | `app/Services/Reports/SpendReport.php`                                                                            |

## Trying the failure modes

Pixelforge reacts to keywords anywhere in the prompt:

| Keyword        | What you'll see                                                                      |
| -------------- | ------------------------------------------------------------------------------------ |
| `[fail]`       | Accepted, then failed. $0 charged, and the hold is returned.                         |
| `[blocked]`    | Rejected under the content policy straight away. $0.                                 |
| `[overloaded]` | 503 on every attempt. "Waiting for capacity", then failed for free after 10 minutes. |
| `[slow]`       | 60 to 90 seconds of processing.                                                      |
| `[duplicate]`  | Every webhook is sent twice, and is still charged once.                              |
| `[succeed]`    | Never fails at random.                                                               |

Submit four or more at once to see the concurrency limit queue them up.
