<script setup lang="ts">
import { computed, ref } from 'vue';
import type { Generation } from '@/types/generations';

const props = defineProps<{
    generation: Generation;
}>();

const failedToLoad = ref(false);

const aspectClass = computed(
    () =>
        ({
            '1024x1024': 'aspect-square',
            '1536x1024': 'aspect-[3/2]',
            '1024x1536': 'aspect-[2/3]',
        })[props.generation.size] ?? 'aspect-square',
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
        class="overflow-hidden rounded-md border bg-muted"
        :class="aspectClass"
    >
        <img
            v-if="showImage"
            :src="generation.imageUrl ?? undefined"
            :alt="generation.prompt"
            loading="lazy"
            class="size-full object-cover"
            @error="failedToLoad = true"
        />
        <div
            v-else
            class="flex size-full items-center justify-center p-4 text-center text-sm text-muted-foreground"
        >
            {{ placeholder }}
        </div>
    </div>
</template>
