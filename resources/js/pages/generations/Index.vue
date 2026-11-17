<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { create } from '@/routes/generations';
import type { Generation } from '@/types/generations';

defineProps<{
    generations: { data: Generation[] };
    hasGenerationsInProgress: boolean;
}>();
</script>

<template>
    <Head title="History" />

    <div class="px-4 py-8 sm:px-6">
        <Heading
            title="History"
            description="Your generations and what they cost."
        />

        <ul v-if="generations.data.length" class="divide-y rounded-lg border">
            <li
                v-for="generation in generations.data"
                :key="generation.id"
                class="flex items-center justify-between gap-4 p-4 text-sm"
            >
                <span class="min-w-0 truncate">{{ generation.prompt }}</span>
                <span class="shrink-0 text-muted-foreground">
                    {{ generation.statusLabel }}
                </span>
            </li>
        </ul>

        <div v-else class="rounded-lg border p-8 text-center">
            <p class="text-sm text-muted-foreground">No generations yet.</p>
            <Button as-child class="mt-4">
                <Link :href="create()">Generate an image</Link>
            </Button>
        </div>
    </div>
</template>
