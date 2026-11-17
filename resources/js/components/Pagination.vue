<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight } from '@lucide/vue';
import { computed } from 'vue';
import type { PaginationMeta } from '@/types/pagination';

const props = defineProps<{
    meta: PaginationMeta;
}>();

/**
 * Laravel's page links without its own Previous/Next entries, which are rendered separately.
 * Gaps come through as "..." with no URL.
 */
const pages = computed(() => props.meta.links.slice(1, -1));

const previousUrl = computed(() => props.meta.links[0]?.url ?? null);
const nextUrl = computed(() => props.meta.links.at(-1)?.url ?? null);

const linkClass =
    'inline-flex h-9 min-w-9 items-center justify-center gap-1 rounded-md border px-3 text-sm font-medium';
</script>

<template>
    <nav
        v-if="meta.last_page > 1"
        class="flex items-center justify-between gap-4"
        aria-label="Pagination"
    >
        <Link
            v-if="previousUrl"
            :href="previousUrl"
            :class="[linkClass, 'hover:bg-accent']"
            rel="prev"
        >
            <ChevronLeft class="size-4" />
            Previous
        </Link>
        <span
            v-else
            :class="[linkClass, 'text-muted-foreground opacity-50']"
            aria-disabled="true"
        >
            <ChevronLeft class="size-4" />
            Previous
        </span>

        <ol class="hidden items-center gap-1 sm:flex">
            <li v-for="(page, index) in pages" :key="index">
                <span
                    v-if="page.url === null"
                    class="px-2 text-sm text-muted-foreground"
                >
                    …
                </span>
                <Link
                    v-else
                    :href="page.url"
                    :class="[
                        linkClass,
                        'tabular-nums',
                        page.active
                            ? 'border-foreground bg-foreground text-background'
                            : 'hover:bg-accent',
                    ]"
                    :aria-current="page.active ? 'page' : undefined"
                >
                    {{ page.label }}
                </Link>
            </li>
        </ol>

        <p class="text-sm text-muted-foreground tabular-nums sm:hidden">
            Page {{ meta.current_page }} of {{ meta.last_page }}
        </p>

        <Link
            v-if="nextUrl"
            :href="nextUrl"
            :class="[linkClass, 'hover:bg-accent']"
            rel="next"
        >
            Next
            <ChevronRight class="size-4" />
        </Link>
        <span
            v-else
            :class="[linkClass, 'text-muted-foreground opacity-50']"
            aria-disabled="true"
        >
            Next
            <ChevronRight class="size-4" />
        </span>
    </nav>
</template>
