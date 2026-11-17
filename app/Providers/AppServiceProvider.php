<?php

namespace App\Providers;

use App\Services\Pixelforge\PixelforgeClient;
use App\Services\Pricing\PricingCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(PricingCalculator::class, fn (): PricingCalculator => new PricingCalculator(
            markupNumerator: (int) config('services.pixelforge.markup.numerator'),
            markupDenominator: (int) config('services.pixelforge.markup.denominator'),
        ));

        $this->app->singleton(PixelforgeClient::class, fn (): PixelforgeClient => new PixelforgeClient(
            baseUrl: (string) config('services.pixelforge.base_url'),
            apiKey: config('services.pixelforge.api_key'),
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
