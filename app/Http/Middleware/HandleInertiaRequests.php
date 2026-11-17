<?php

namespace App\Http\Middleware;

use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
            ],
            'credits' => fn (): ?array => $request->user() === null ? null : [
                'balance' => [
                    'exact' => Money::toDecimal($request->user()->balance),
                    'display' => Money::format($request->user()->balance, 2),
                ],
                'balanceNanodollars' => $request->user()->balance,
            ],
            'notifications' => fn (): ?array => $request->user() === null ? null : [
                'unreadCount' => $request->user()->unreadNotifications()->count(),
                'recent' => $request->user()->notifications()
                    ->latest()
                    ->limit(8)
                    ->get()
                    ->map(fn (DatabaseNotification $notification): array => [
                        ...$notification->data,
                        'id' => $notification->id,
                        'read' => $notification->read_at !== null,
                        'createdAt' => $notification->created_at?->toIso8601ZuluString(),
                    ])
                    ->all(),
            ],
            // Lets the header poll for news only while something is still running.
            'generationsInProgress' => fn (): bool => (bool) $request->user()?->generations()->inProgress()->exists(),
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
