<?php

namespace App\Http\Controllers;

use App\Actions\Generations\CreateGeneration;
use App\Enums\ImageQuality;
use App\Enums\ImageSize;
use App\Http\Requests\StoreGenerationRequest;
use App\Http\Resources\GenerationResource;
use App\Services\Pixelforge\PixelforgeClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GenerationController extends Controller
{
    /**
     * The user's generations, newest first.
     */
    public function index(Request $request): Response
    {
        $generations = $request->user()->generations()
            ->latest()
            ->orderByDesc('id')
            ->paginate(24);

        return Inertia::render('generations/Index', [
            'generations' => GenerationResource::collection($generations),
            'hasGenerationsInProgress' => $request->user()->generations()->inProgress()->exists(),
        ]);
    }

    /**
     * The generate page, optionally prefilled from one of the user's earlier generations.
     */
    public function create(Request $request, PixelforgeClient $pixelforge): Response
    {
        $source = $request->filled('from')
            ? $request->user()->generations()->find($request->string('from')->toString())
            : null;

        return Inertia::render('generations/Create', [
            'pricing' => $this->pricingProps($pixelforge),
            'sizes' => array_map(fn (ImageSize $size): array => [
                'value' => $size->value,
                'label' => $size->label(),
            ], ImageSize::cases()),
            'qualities' => array_map(fn (ImageQuality $quality): array => [
                'value' => $quality->value,
                'label' => $quality->label(),
            ], ImageQuality::cases()),
            'limits' => [
                'prompt' => StoreGenerationRequest::MAX_PROMPT_CHARACTERS,
                'referenceImages' => StoreGenerationRequest::MAX_REFERENCE_IMAGES,
            ],
            'defaults' => [
                'prompt' => $source->prompt ?? '',
                'size' => $source?->size->value ?? ImageSize::Square->value,
                'quality' => $source?->quality->value ?? ImageQuality::Medium->value,
                'reference_images' => $source->reference_images ?? [],
            ],
        ]);
    }

    public function store(StoreGenerationRequest $request, CreateGeneration $createGeneration): RedirectResponse
    {
        $createGeneration->handle(
            $request->user(),
            $request->string('prompt')->toString(),
            $request->enum('size', ImageSize::class),
            $request->enum('quality', ImageQuality::class),
            $request->referenceImages(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Generation queued. You only pay if it succeeds.']);

        return to_route('generations.index');
    }

    /**
     * The pricing tables for the live estimate, in nanodollars so the browser never does float money math.
     *
     * @return array<string, mixed>
     */
    private function pricingProps(PixelforgeClient $pixelforge): array
    {
        $pricing = $pixelforge->pricing();

        return [
            'ratesPerMillion' => [
                'inputText' => $pricing->rates->inputTextPerMillion,
                'inputImage' => $pricing->rates->inputImagePerMillion,
                'output' => $pricing->rates->outputPerMillion,
                'reasoning' => $pricing->rates->reasoningPerMillion,
            ],
            'charactersPerTextToken' => $pricing->charactersPerTextToken,
            'textTokenVarianceBasisPoints' => $pricing->textTokenVarianceBasisPoints,
            'tokensPerReferenceImage' => $pricing->tokensPerReferenceImage,
            'outputTokens' => $pricing->outputTokens,
            'reasoningTokens' => $pricing->reasoningTokens,
            'markup' => [
                'numerator' => (int) config('services.pixelforge.markup.numerator'),
                'denominator' => (int) config('services.pixelforge.markup.denominator'),
            ],
        ];
    }
}
