<script setup lang="ts">
import { Head, Link, usePoll } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import GenerationCost from '@/components/generations/GenerationCost.vue';
import GenerationDetails from '@/components/generations/GenerationDetails.vue';
import GenerationImage from '@/components/generations/GenerationImage.vue';
import Heading from '@/components/Heading.vue';
import Pagination from '@/components/Pagination.vue';
import { Button } from '@/components/ui/button';
import { formatDateTime } from '@/lib/dates';
import { create } from '@/routes/generations';
import type { Generation } from '@/types/generations';
import type { Paginated } from '@/types/pagination';

const props = defineProps<{
    generations: Paginated<Generation>;
    hasGenerationsInProgress: boolean;
}>();

const POLL_INTERVAL_MS = 3000;

// Refresh while anything is still running, and stop as soon as everything has finished.
const { start, stop } = usePoll(
    POLL_INTERVAL_MS,
    { only: ['generations', 'hasGenerationsInProgress'] },
    { autoStart: false },
);

watch(
    () => props.hasGenerationsInProgress,
    (inProgress) => (inProgress ? start() : stop()),
    { immediate: true },
);

const selectedId = ref<string | null>(null);

// Look the selection up in the live list so an open dialog updates as the generation finishes.
const selected = computed(
    () =>
        props.generations.data.find(({ id }) => id === selectedId.value) ??
        null,
);

const detailsOpen = computed({
    get: () => selected.value !== null,
    set: (open: boolean) => {
        if (!open) {
            selectedId.value = null;
        }
    },
});
</script>

<template>
    <Head title="History" />

    <div class="px-4 py-8 sm:px-6">
        <div class="flex items-start justify-between gap-4">
            <Heading
                title="History"
                description="Your generations, what they were estimated to cost and what you paid."
            />
            <Button as-child class="shrink-0">
                <Link :href="create()">New</Link>
            </Button>
        </div>

        <ul
            v-if="generations.data.length"
            class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3"
        >
            <li v-for="generation in generations.data" :key="generation.id">
                <article class="flex h-full flex-col gap-3">
                    <button
                        type="button"
                        class="block w-full rounded-md text-left focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                        :aria-label="`Show details for: ${generation.prompt}`"
                        @click="selectedId = generation.id"
                    >
                        <GenerationImage :generation="generation" />
                    </button>

                    <div class="flex items-start justify-between gap-3">
                        <p class="line-clamp-2 min-w-0 text-sm">
                            {{ generation.prompt }}
                        </p>
                        <span
                            class="shrink-0 rounded-full border px-2 py-0.5 text-xs font-medium"
                            :class="{
                                'border-foreground bg-foreground text-background':
                                    generation.status === 'succeeded',
                                'border-dashed': !generation.isFinal,
                            }"
                        >
                            {{ generation.statusLabel }}
                        </span>
                    </div>

                    <p class="text-xs text-muted-foreground">
                        {{ generation.sizeLabel }} ·
                        {{ generation.qualityLabel }}
                        <template v-if="generation.referenceImages.length">
                            · {{ generation.referenceImages.length }}
                            {{
                                generation.referenceImages.length === 1
                                    ? 'reference'
                                    : 'references'
                            }}
                        </template>
                        · {{ formatDateTime(generation.createdAt) }}
                    </p>

                    <p v-if="generation.failureReason" class="text-sm">
                        {{ generation.failureReason }}
                    </p>

                    <div class="mt-auto border-t pt-3">
                        <GenerationCost :generation="generation" />
                    </div>

                    <div class="flex gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            @click="selectedId = generation.id"
                        >
                            Details
                        </Button>
                        <Button
                            v-if="generation.status === 'failed'"
                            as-child
                            variant="outline"
                            size="sm"
                        >
                            <Link
                                :href="
                                    create({ query: { from: generation.id } })
                                "
                            >
                                Try again
                            </Link>
                        </Button>
                    </div>
                </article>
            </li>
        </ul>

        <div v-else class="rounded-lg border p-10 text-center">
            <p class="font-medium">No generations yet</p>
            <p class="mt-1 text-sm text-muted-foreground">
                You'll see each image here with its status and cost.
            </p>
            <Button as-child class="mt-4">
                <Link :href="create()">Generate an image</Link>
            </Button>
        </div>

        <Pagination :meta="generations.meta" class="mt-10" />

        <GenerationDetails v-model:open="detailsOpen" :generation="selected" />
    </div>
</template>
