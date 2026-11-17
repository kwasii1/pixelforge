<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Reports\SpendReport;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    /**
     * Pixelforge reports at most 92 days per request.
     */
    private const MAX_DAYS = 92;

    public function __invoke(Request $request, SpendReport $report): Response
    {
        $today = CarbonImmutable::now('UTC')->toDateString();

        $validated = $request->validate([
            'start_date' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:end_date'],
            'end_date' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:'.$today],
        ]);

        $endDate = CarbonImmutable::parse($validated['end_date'] ?? $today, 'UTC');
        $startDate = CarbonImmutable::parse($validated['start_date'] ?? $endDate->subDays(29)->toDateString(), 'UTC');

        if ($startDate->diffInDays($endDate) + 1 > self::MAX_DAYS) {
            $startDate = $endDate->subDays(self::MAX_DAYS - 1);
        }

        return Inertia::render('admin/Report', [
            'filters' => [
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
                'max_date' => $today,
            ],
            ...$report->build($startDate, $endDate),
        ]);
    }
}
