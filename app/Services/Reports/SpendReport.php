<?php

namespace App\Services\Reports;

use App\Enums\GenerationStatus;
use App\Models\Generation;
use App\Models\User;
use App\Services\Pixelforge\PixelforgeClient;
use App\Services\Pixelforge\PixelforgeException;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * What users were charged, what Pixelforge charged us, and the profit, per UTC day and per user.
 *
 * Generations count on the UTC day they completed, as Pixelforge bills them,
 * so each day's provider cost can be compared with GET /v1/usage directly.
 * Only succeeded generations cost anything.
 */
class SpendReport
{
    private const USAGE_CACHE_SECONDS = 60;

    public function __construct(private readonly PixelforgeClient $pixelforge) {}

    /**
     * @return array{days: list<array<string, mixed>>, users: list<array<string, mixed>>, totals: array<string, mixed>, pixelforgeUsageError: string|null}
     */
    public function build(CarbonImmutable $startDate, CarbonImmutable $endDate): array
    {
        [$reported, $usageError] = $this->pixelforgeCosts($startDate, $endDate);

        $daily = $this->succeededBetween($startDate, $endDate)
            ->selectRaw('DATE(completed_at) as day, COUNT(*) as generations, SUM(price) as spend, SUM(provider_cost) as cost')
            ->groupBy('day')
            ->get()
            ->keyBy(fn (Generation $row): string => (string) $row->getAttribute('day'));

        $days = collect(CarbonPeriod::create($startDate, $endDate)->toArray())
            ->map(function ($date) use ($daily, $reported, $usageError): array {
                $day = $date->format('Y-m-d');
                $row = $daily->get($day);

                return $this->row(
                    ['date' => $day],
                    (int) $row?->getAttribute('generations'),
                    (int) $row?->getAttribute('spend'),
                    (int) $row?->getAttribute('cost'),
                    $usageError === null ? ($reported[$day] ?? 0) : null,
                );
            })
            ->reverse()
            ->all();

        $byUser = $this->succeededBetween($startDate, $endDate)
            ->selectRaw('user_id, COUNT(*) as generations, SUM(price) as spend, SUM(provider_cost) as cost')
            ->groupBy('user_id')
            ->orderByRaw('SUM(price) desc')
            ->get();

        $users = User::query()->whereIn('id', $byUser->pluck('user_id'))->get(['id', 'name', 'email'])->keyBy('id');

        return [
            'days' => array_values($days),
            'users' => array_values($byUser->map(fn (Generation $row): array => $this->row(
                [
                    'id' => $row->user_id,
                    'name' => $users->get($row->user_id)?->name,
                    'email' => $users->get($row->user_id)?->email,
                ],
                (int) $row->getAttribute('generations'),
                (int) $row->getAttribute('spend'),
                (int) $row->getAttribute('cost'),
            ))->all()),
            'totals' => $this->row(
                [],
                (int) $daily->sum(fn (Generation $row): int => (int) $row->getAttribute('generations')),
                (int) $daily->sum(fn (Generation $row): int => (int) $row->getAttribute('spend')),
                (int) $daily->sum(fn (Generation $row): int => (int) $row->getAttribute('cost')),
                $usageError === null ? array_sum($reported) : null,
            ),
            'pixelforgeUsageError' => $usageError,
        ];
    }

    /**
     * @return Builder<Generation>
     */
    private function succeededBetween(CarbonImmutable $startDate, CarbonImmutable $endDate): Builder
    {
        return Generation::query()
            ->where('status', GenerationStatus::Succeeded)
            ->whereBetween('completed_at', [$startDate->startOfDay(), $endDate->endOfDay()]);
    }

    /**
     * Pixelforge's own record of what it billed per day, in nanodollars.
     *
     * @return array{0: array<string, int>, 1: string|null}
     */
    private function pixelforgeCosts(CarbonImmutable $startDate, CarbonImmutable $endDate): array
    {
        try {
            $usage = Cache::remember(
                "pixelforge.usage.{$startDate->toDateString()}.{$endDate->toDateString()}",
                self::USAGE_CACHE_SECONDS,
                fn (): array => $this->pixelforge->usage($startDate->toDateString(), $endDate->toDateString()),
            );
        } catch (PixelforgeException $exception) {
            return [[], "Couldn't load Pixelforge's usage report: {$exception->getMessage()}"];
        }

        return [
            Collection::make($usage['data'])
                ->mapWithKeys(fn (array $day): array => [$day['date'] => Money::fromDecimal($day['cost_usd'])])
                ->all(),
            null,
        ];
    }

    /**
     * @param  array<string, mixed>  $identity
     * @return array<string, mixed>
     */
    private function row(array $identity, int $generations, int $spend, int $cost, ?int $reportedCost = null): array
    {
        $row = [
            ...$identity,
            'generations' => $generations,
            'spend' => self::money($spend),
            'providerCost' => self::money($cost),
            'profit' => self::money($spend - $cost),
        ];

        if (array_key_exists('date', $identity) || $identity === []) {
            $row['pixelforgeCost'] = $reportedCost === null ? null : self::money($reportedCost);
            $row['difference'] = $reportedCost === null ? null : self::money($cost - $reportedCost);
        }

        return $row;
    }

    /**
     * @return array{exact: string, display: string}
     */
    private static function money(int $nanodollars): array
    {
        return ['exact' => Money::toDecimal($nanodollars), 'display' => Money::format($nanodollars)];
    }
}
