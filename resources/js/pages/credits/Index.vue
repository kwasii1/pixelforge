<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { store } from '@/actions/App/Http/Controllers/CreditController';
import Heading from '@/components/Heading.vue';
import Pagination from '@/components/Pagination.vue';
import { Button } from '@/components/ui/button';
import { formatDateTime } from '@/lib/dates';
import type { Money } from '@/types/generations';
import type { Paginated } from '@/types/pagination';

type Transaction = {
    id: string;
    label: string;
    type: 'purchase' | 'hold' | 'release' | 'charge';
    prompt: string | null;
    generationId: string | null;
    amount: Money;
    createdAt: string;
};

defineProps<{
    packs: number[];
    transactions: Paginated<Transaction>;
}>();

const page = usePage();
const credits = computed(() => page.props.credits);

const purchase = useForm({ dollars: 0 });

function buy(dollars: number): void {
    purchase.dollars = dollars;
    purchase.post(store.url(), { preserveScroll: true });
}
</script>

<template>
    <Head title="Credits" />

    <div class="px-4 py-8 sm:px-6">
        <Heading
            title="Credits"
            description="Credits are US dollars. Each generation holds the most it can cost, then you're charged what it actually cost. Failed generations are free."
        />

        <section class="grid gap-6 rounded-lg border p-5 sm:grid-cols-2">
            <div class="grid gap-1">
                <h2 class="text-sm font-medium text-muted-foreground">
                    Balance
                </h2>
                <p
                    class="text-3xl font-semibold tracking-tight tabular-nums"
                    :title="credits ? `$${credits.balance.exact}` : undefined"
                >
                    {{ credits?.balance.display }}
                </p>
            </div>

            <div class="grid gap-2">
                <h2 class="text-sm font-medium text-muted-foreground">
                    Buy credits
                </h2>
                <div class="grid grid-cols-3 gap-2">
                    <Button
                        v-for="dollars in packs"
                        :key="dollars"
                        variant="outline"
                        class="tabular-nums"
                        :disabled="purchase.processing"
                        @click="buy(dollars)"
                    >
                        ${{ dollars }}
                    </Button>
                </div>
                <p class="text-xs text-muted-foreground">
                    Demo purchase: no payment is taken.
                </p>
            </div>
        </section>

        <section class="mt-10">
            <h2 class="mb-3 text-base font-medium">Activity</h2>

            <ul
                v-if="transactions.data.length"
                class="divide-y rounded-lg border"
            >
                <li
                    v-for="transaction in transactions.data"
                    :key="transaction.id"
                    class="flex items-start justify-between gap-4 p-4 text-sm"
                >
                    <div class="min-w-0">
                        <p class="font-medium">{{ transaction.label }}</p>
                        <p
                            v-if="transaction.prompt"
                            class="truncate text-muted-foreground"
                        >
                            {{ transaction.prompt }}
                        </p>
                        <p class="text-xs text-muted-foreground">
                            {{ formatDateTime(transaction.createdAt) }}
                        </p>
                    </div>
                    <span
                        class="shrink-0 tabular-nums"
                        :class="{
                            'font-semibold': transaction.type === 'charge',
                            'text-muted-foreground':
                                transaction.type === 'hold' ||
                                transaction.type === 'release',
                        }"
                        :title="`$${transaction.amount.exact}`"
                    >
                        {{
                            transaction.amount.display.startsWith('-')
                                ? transaction.amount.display
                                : `+${transaction.amount.display}`
                        }}
                    </span>
                </li>
            </ul>
            <p v-else class="rounded-lg border p-8 text-center text-sm">
                No activity yet. Buy credits to start generating.
            </p>

            <Pagination :meta="transactions.meta" class="mt-6" />
        </section>
    </div>
</template>
