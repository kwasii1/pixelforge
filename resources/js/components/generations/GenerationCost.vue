<script setup lang="ts">
import type { Generation } from '@/types/generations';

defineProps<{
    generation: Generation;
}>();
</script>

<template>
    <dl class="grid grid-cols-[auto_1fr] gap-x-4 gap-y-1 text-sm">
        <dt class="text-muted-foreground">Estimated</dt>
        <dd class="text-right tabular-nums">
            {{ generation.estimate.min.display }} –
            {{ generation.estimate.max.display }}
        </dd>

        <dt class="text-muted-foreground">Charged</dt>
        <dd class="text-right tabular-nums">
            <span
                v-if="generation.status === 'succeeded' && generation.price"
                class="font-semibold"
                :title="`$${generation.price.exact}`"
            >
                {{ generation.price.display }}
            </span>
            <span v-else-if="generation.status === 'failed'">
                $0 · not charged
            </span>
            <span v-else class="text-muted-foreground">When finished</span>
        </dd>
    </dl>
</template>
