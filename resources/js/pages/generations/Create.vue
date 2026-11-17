<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { Plus, X } from '@lucide/vue';
import { computed } from 'vue';
import { store } from '@/actions/App/Http/Controllers/GenerationController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import SegmentedControl from '@/components/SegmentedControl.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useCostEstimate } from '@/composables/useCostEstimate';
import { formatNanodollars } from '@/lib/money';
import { index as creditsIndex } from '@/routes/credits';
import type { Option, Pricing } from '@/types/generations';

const props = defineProps<{
    pricing: Pricing;
    sizes: Option[];
    qualities: Option[];
    limits: { prompt: number; referenceImages: number };
    defaults: {
        prompt: string;
        size: string;
        quality: string;
        reference_images: string[];
    };
}>();

const form = useForm({
    prompt: props.defaults.prompt,
    size: props.defaults.size,
    quality: props.defaults.quality,
    reference_images: [...props.defaults.reference_images],
});

// Unicode code points, matching PHP's mb_strlen on the server.
const promptLength = computed(() => Array.from(form.prompt).length);

const estimate = useCostEstimate(
    () => props.pricing,
    () => ({
        prompt: form.prompt,
        size: form.size,
        quality: form.quality,
        referenceImageCount: form.reference_images.filter(
            (url) => url.trim() !== '',
        ).length,
    }),
);

const minimumPrice = computed(() =>
    formatNanodollars(estimate.value.minimumPrice),
);
const maximumPrice = computed(() =>
    formatNanodollars(estimate.value.maximumPrice),
);

const page = usePage();
const credits = computed(() => page.props.credits);

const canAfford = computed(
    () =>
        (credits.value?.balanceNanodollars ?? 0) >= estimate.value.maximumPrice,
);

const markupPercent = computed(
    () =>
        ((props.pricing.markup.numerator - props.pricing.markup.denominator) /
            props.pricing.markup.denominator) *
        100,
);

const breakdown = computed(() => {
    const { minimum, maximum } = estimate.value;
    const range = (low: number, high: number) =>
        low === high
            ? low.toLocaleString('en-US')
            : `${low.toLocaleString('en-US')}–${high.toLocaleString('en-US')}`;

    return [
        {
            label: 'Prompt text',
            tokens: range(minimum.inputText, maximum.inputText),
        },
        {
            label: 'Reference images',
            tokens: range(minimum.inputImage, maximum.inputImage),
        },
        {
            label: 'Image output',
            tokens: range(minimum.output, maximum.output),
        },
        {
            label: 'Reasoning',
            tokens: range(minimum.reasoning, maximum.reasoning),
        },
    ];
});

const aspectClasses: Record<string, string> = {
    '1024x1024': 'h-3.5 w-3.5',
    '1536x1024': 'h-3 w-[1.125rem]',
    '1024x1536': 'h-[1.125rem] w-3',
};

// Bound from script: a literal URL attribute here trips vue-tsc's template parsing.
const referenceImagePlaceholder = 'https://example.com/style.png';

const canAddReferenceImage = computed(
    () => form.reference_images.length < props.limits.referenceImages,
);

function addReferenceImage(): void {
    if (canAddReferenceImage.value) {
        form.reference_images.push('');
    }
}

// Errors for keys outside the form's fields, such as "reference_images.0" or "credits".
function fieldError(key: string): string | undefined {
    return (form.errors as Record<string, string | undefined>)[key];
}

function referenceImageError(index: number): string | undefined {
    return fieldError(`reference_images.${index}`);
}

function removeReferenceImage(index: number): void {
    form.reference_images.splice(index, 1);
    form.clearErrors();
}

function submit(): void {
    form.post(store.url(), { preserveScroll: true });
}
</script>

<template>
    <Head title="Generate" />

    <div class="px-4 py-8 pb-28 sm:px-6 lg:pb-8">
        <Heading
            title="Generate"
            description="Describe an image, pick a size and quality, and see the price before you submit."
        />

        <form
            class="grid gap-8 lg:grid-cols-[minmax(0,1fr)_20rem]"
            @submit.prevent="submit"
        >
            <div class="flex flex-col gap-6">
                <div class="grid gap-2">
                    <div class="flex items-baseline justify-between">
                        <Label for="prompt">Prompt</Label>
                        <span
                            class="text-xs text-muted-foreground tabular-nums"
                            :class="{
                                'font-medium text-foreground':
                                    promptLength > limits.prompt,
                            }"
                        >
                            {{ promptLength.toLocaleString('en-US') }} /
                            {{ limits.prompt.toLocaleString('en-US') }}
                        </span>
                    </div>
                    <textarea
                        id="prompt"
                        v-model="form.prompt"
                        rows="6"
                        required
                        placeholder="A lighthouse on a cliff at dawn, oil painting"
                        class="w-full resize-y rounded-md border border-input bg-transparent px-3 py-2 text-base outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 md:text-sm dark:bg-input/30"
                        :aria-invalid="Boolean(form.errors.prompt)"
                        @keydown.meta.enter.prevent="submit"
                        @keydown.ctrl.enter.prevent="submit"
                    ></textarea>
                    <InputError :message="form.errors.prompt" />
                </div>

                <div class="grid gap-2">
                    <Label id="size-label">Size</Label>
                    <SegmentedControl
                        v-model="form.size"
                        name="size"
                        label="Size"
                        :options="sizes"
                    >
                        <template #option="{ option }">
                            <span
                                aria-hidden="true"
                                class="rounded-[2px] border-[1.5px] border-current"
                                :class="aspectClasses[option.value]"
                            />
                            <span>{{ option.label }}</span>
                        </template>
                    </SegmentedControl>
                    <p class="text-xs text-muted-foreground tabular-nums">
                        {{ form.size.replace('x', ' × ') }} px
                    </p>
                    <InputError :message="form.errors.size" />
                </div>

                <div class="grid gap-2">
                    <Label>Quality</Label>
                    <SegmentedControl
                        v-model="form.quality"
                        name="quality"
                        label="Quality"
                        :options="qualities"
                    />
                    <InputError :message="form.errors.quality" />
                </div>

                <div class="grid gap-2">
                    <div class="flex items-baseline justify-between">
                        <Label>Reference images</Label>
                        <span class="text-xs text-muted-foreground">
                            Optional · up to {{ limits.referenceImages }}
                        </span>
                    </div>
                    <p class="text-sm text-muted-foreground">
                        Image URLs that guide the style. Each adds
                        {{
                            pricing.tokensPerReferenceImage.toLocaleString(
                                'en-US',
                            )
                        }}
                        input tokens.
                    </p>

                    <div
                        v-for="(_url, index) in form.reference_images"
                        :key="index"
                        class="grid gap-1"
                    >
                        <div class="flex gap-2">
                            <Input
                                v-model="form.reference_images[index]"
                                type="url"
                                inputmode="url"
                                :placeholder="referenceImagePlaceholder"
                                :aria-label="`Reference image ${index + 1}`"
                                :aria-invalid="
                                    Boolean(referenceImageError(index))
                                "
                            />
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                class="shrink-0"
                                @click="removeReferenceImage(index)"
                            >
                                <X />
                                <span class="sr-only">
                                    Remove reference image {{ index + 1 }}
                                </span>
                            </Button>
                        </div>
                        <InputError :message="referenceImageError(index)" />
                    </div>

                    <div>
                        <Button
                            v-if="canAddReferenceImage"
                            type="button"
                            variant="outline"
                            size="sm"
                            @click="addReferenceImage"
                        >
                            <Plus />
                            Add reference image
                        </Button>
                    </div>
                    <InputError :message="form.errors.reference_images" />
                </div>
            </div>

            <aside class="lg:sticky lg:top-6 lg:self-start">
                <section
                    class="flex flex-col gap-4 rounded-lg border p-5"
                    aria-labelledby="cost-heading"
                >
                    <div class="grid gap-1">
                        <h2
                            id="cost-heading"
                            class="text-sm font-medium text-muted-foreground"
                        >
                            Estimated cost
                        </h2>
                        <p
                            class="text-2xl font-semibold tracking-tight tabular-nums"
                            aria-live="polite"
                        >
                            {{ minimumPrice }} – {{ maximumPrice }}
                        </p>
                        <p class="text-sm">
                            You'll pay at most
                            <span class="font-semibold tabular-nums">{{
                                maximumPrice
                            }}</span
                            >. Failed generations are free.
                        </p>
                    </div>

                    <table class="w-full text-sm">
                        <caption class="sr-only">
                            Estimated tokens
                        </caption>
                        <tbody class="divide-y">
                            <tr v-for="row in breakdown" :key="row.label">
                                <th
                                    scope="row"
                                    class="py-1.5 text-left font-normal text-muted-foreground"
                                >
                                    {{ row.label }}
                                </th>
                                <td class="py-1.5 text-right tabular-nums">
                                    {{ row.tokens }}
                                    <span class="text-muted-foreground"
                                        >tokens</span
                                    >
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <details class="text-sm">
                        <summary class="cursor-pointer font-medium">
                            Why a range?
                        </summary>
                        <p class="mt-2 text-muted-foreground">
                            Pixelforge bills per token. Image tokens are fixed
                            by size and quality, but the exact prompt and
                            reasoning token counts are only known once the image
                            is finished. You're charged the actual Pixelforge
                            cost plus {{ markupPercent }}%, which is never more
                            than the top of this range.
                        </p>
                    </details>

                    <div class="grid gap-2 border-t pt-4 text-sm">
                        <div class="flex justify-between">
                            <span class="text-muted-foreground">Balance</span>
                            <span class="tabular-nums">{{
                                credits?.balance.display
                            }}</span>
                        </div>
                        <p v-if="!canAfford" role="alert">
                            Your balance doesn't cover the most this could cost.
                            <Link
                                :href="creditsIndex()"
                                class="font-medium underline underline-offset-4"
                            >
                                Buy credits
                            </Link>
                        </p>
                        <InputError :message="fieldError('credits')" />
                    </div>

                    <Button
                        type="submit"
                        class="hidden w-full lg:inline-flex"
                        :disabled="form.processing || !canAfford"
                    >
                        <Spinner v-if="form.processing" />
                        Generate
                    </Button>
                </section>
            </aside>

            <div
                class="fixed inset-x-0 bottom-0 z-10 border-t bg-background px-4 py-3 lg:hidden"
            >
                <div class="mx-auto flex max-w-5xl items-center gap-4">
                    <div class="min-w-0 flex-1 text-sm">
                        <p class="text-muted-foreground">At most</p>
                        <p class="font-semibold tabular-nums">
                            {{ maximumPrice }}
                        </p>
                    </div>
                    <Button
                        v-if="canAfford"
                        type="submit"
                        :disabled="form.processing"
                    >
                        <Spinner v-if="form.processing" />
                        Generate
                    </Button>
                    <Button v-else as-child>
                        <Link :href="creditsIndex()">Buy credits</Link>
                    </Button>
                </div>
            </div>
        </form>
    </div>
</template>
