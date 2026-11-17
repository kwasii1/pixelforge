<?php

namespace App\Http\Requests;

use App\Enums\ImageQuality;
use App\Enums\ImageSize;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGenerationRequest extends FormRequest
{
    public const MAX_PROMPT_CHARACTERS = 4000;

    public const MAX_REFERENCE_IMAGES = 4;

    /**
     * Drop blank reference image rows the form may send.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'reference_images' => array_values(array_filter(
                (array) $this->input('reference_images', []),
                fn (mixed $url): bool => is_string($url) && trim($url) !== '',
            )),
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'prompt' => ['required', 'string', 'max:'.self::MAX_PROMPT_CHARACTERS],
            'size' => ['required', Rule::enum(ImageSize::class)],
            'quality' => ['required', Rule::enum(ImageQuality::class)],
            'reference_images' => ['array', 'max:'.self::MAX_REFERENCE_IMAGES],
            'reference_images.*' => ['string', 'distinct', 'max:2048', 'url:http,https'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reference_images.max' => 'You can add at most :max reference images.',
            'reference_images.*.url' => 'Each reference image must be an http or https URL.',
            'reference_images.*.distinct' => 'Each reference image can only be added once.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['reference_images.*' => 'reference image'];
    }

    /**
     * @return list<string>
     */
    public function referenceImages(): array
    {
        return array_values(array_map(trim(...), $this->validated('reference_images', [])));
    }
}
