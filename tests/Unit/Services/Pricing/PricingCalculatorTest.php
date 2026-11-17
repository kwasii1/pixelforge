<?php

use App\Enums\ImageQuality;
use App\Enums\ImageSize;
use App\Services\Pricing\PricingCalculator;
use App\Services\Pricing\PricingTable;
use App\Services\Pricing\Rates;
use App\Services\Pricing\TokenUsage;

function publishedPricing(): PricingTable
{
    return PricingTable::fromArray(json_decode(
        file_get_contents(__DIR__.'/../../../Fixtures/pixelforge/pricing.json'),
        true,
    ));
}

function publishedRates(): Rates
{
    return Rates::fromArray([
        'input_text_tokens' => '5.00',
        'input_image_tokens' => '10.00',
        'output_tokens' => '40.00',
        'reasoning_tokens' => '12.50',
    ]);
}

it('prices the succeeded generation from the docs exactly', function () {
    $usage = new TokenUsage(inputText: 12, inputImage: 765, output: 6450, reasoning: 3184);

    expect((new PricingCalculator)->providerCost($usage, publishedRates()))->toBe(305_510_000);
});

it('matches the cost Pixelforge reports in the usage example', function () {
    $usage = new TokenUsage(inputText: 41, inputImage: 0, output: 3300, reasoning: 2950);

    expect((new PricingCalculator)->providerCost($usage, publishedRates()))->toBe(169_080_000);
});

it('charges the user the provider cost plus 25 percent', function () {
    expect((new PricingCalculator)->userPrice(305_510_000))->toBe(381_887_500);
});

it('rounds the user price up when the markup leaves a fraction of a nanodollar', function () {
    expect((new PricingCalculator)->userPrice(1))->toBe(2);
});

it('rounds the provider cost up when rates are finer than a nanodollar per token', function () {
    $rates = new Rates(inputTextPerMillion: 1, inputImagePerMillion: 0, outputPerMillion: 0, reasoningPerMillion: 0);
    $usage = new TokenUsage(inputText: 1, inputImage: 0, output: 0, reasoning: 0);

    expect((new PricingCalculator)->providerCost($usage, $rates))->toBe(1);
});

it('applies a configured markup', function () {
    expect((new PricingCalculator(markupNumerator: 3, markupDenominator: 2))->userPrice(100))->toBe(150);
});

it('estimates the most a generation can cost as in the docs worked example', function () {
    $estimate = (new PricingCalculator)->estimate(
        publishedPricing(),
        str_repeat('a', 400),
        ImageSize::Landscape,
        ImageQuality::High,
        referenceImageCount: 2,
    );

    expect($estimate->maximumUsage->toArray())->toBe([
        'input_text_tokens' => 115,
        'input_image_tokens' => 1530,
        'output_tokens' => 6450,
        'reasoning_tokens' => 8000,
        'total_tokens' => 16095,
    ])
        ->and($estimate->maximumCost)->toBe(373_875_000)
        ->and($estimate->maximumPrice)->toBe(467_343_750);
});

it('estimates the least a generation can cost from the low end of each range', function () {
    $estimate = (new PricingCalculator)->estimate(
        publishedPricing(),
        str_repeat('a', 400),
        ImageSize::Landscape,
        ImageQuality::High,
        referenceImageCount: 2,
    );

    expect($estimate->minimumUsage->inputText)->toBe(85)
        ->and($estimate->minimumUsage->reasoning)->toBe(1000)
        ->and($estimate->minimumCost)->toBe(286_225_000)
        ->and($estimate->minimumPrice)->toBe(357_781_250);
});

it('counts prompt characters rather than bytes', function () {
    $estimate = (new PricingCalculator)->estimate(
        publishedPricing(),
        str_repeat('é', 8),
        ImageSize::Square,
        ImageQuality::Low,
        referenceImageCount: 0,
    );

    expect($estimate->maximumUsage->inputText)->toBe(3)
        ->and($estimate->minimumUsage->inputText)->toBe(1);
});

it('uses the output tokens for the chosen size and quality', function (ImageQuality $quality, ImageSize $size, int $outputTokens) {
    $estimate = (new PricingCalculator)->estimate(publishedPricing(), 'a cat', $size, $quality, 0);

    expect($estimate->maximumUsage->output)->toBe($outputTokens);
})->with([
    [ImageQuality::Low, ImageSize::Square, 280],
    [ImageQuality::Medium, ImageSize::Portrait, 1650],
    [ImageQuality::High, ImageSize::Square, 4300],
]);
