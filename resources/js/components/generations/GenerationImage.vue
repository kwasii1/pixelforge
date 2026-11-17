<script setup lang="ts">
import { computed, ref } from 'vue';
import type { Generation } from '@/types/generations';

const props = withDefaults(
    defineProps<{
        generation: Generation;
        /**
         * "tile": a square frame for grid cards. "preview": a fixed-height frame for the details dialog.
         * Either way every orientation gets the same frame and the whole image is shown, letterboxed.
         */
        variant?: 'tile' | 'preview';
    }>(),
    { variant: 'tile' },
);

const failedToLoad = ref(false);

const frameClass = computed(() =>
    props.variant === 'preview' ? 'h-[min(60dvh,32rem)]' : 'aspect-square',
);

const showImage = computed(
    () =>
        props.generation.imageUrl !== null &&
        !props.generation.imageExpired &&
        !failedToLoad.value,
);

const placeholder = computed(() => {
    const { status, statusLabel } = props.generation;

    if (status === 'succeeded') {
        return 'Image expired';
    }

    if (status === 'failed') {
        return 'No image';
    }

    return statusLabel;
});
</script>

<template>
    <div
        class="flex w-full items-center justify-center overflow-hidden rounded-md border bg-muted"
        :class="frameClass"
    >
        <img
            v-if="showImage"
            :src="generation.imageUrl ?? undefined"
            :alt="generation.prompt"
            loading="lazy"
            class="size-full object-contain"
            @error="failedToLoad = true"
        />
        <p v-else class="p-4 text-center text-sm text-muted-foreground">
            {{ placeholder }}
        </p>
    </div>
</template>
