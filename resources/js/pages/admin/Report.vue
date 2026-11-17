<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { reactive } from 'vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { report } from '@/routes/admin';
import type { Money } from '@/types/generations';

type Totals = {
    generations: number;
    spend: Money;
    providerCost: Money;
    profit: Money;
};

type DayRow = Totals & {
    date: string;
    pixelforgeCost: Money | null;
    difference: Money | null;
};

type UserRow = Totals & {
    id: number;
    name: string | null;
    email: string | null;
};

const props = defineProps<{
    filters: { start_date: string; end_date: string; max_date: string };
    days: DayRow[];
    users: UserRow[];
    totals: DayRow;
    pixelforgeUsageError: string | null;
}>();

const range = reactive({
    start_date: props.filters.start_date,
    end_date: props.filters.end_date,
});

function apply(): void {
    router.get(report.url(), range, { preserveState: true });
}

const matches = (row: DayRow): boolean =>
    row.difference !== null && row.difference.exact === '0.000000000';
</script>

<template>
    <Head title="Report" />

    <div class="px-4 py-8 sm:px-6">
        <Heading
            title="Operator report"
            description="Succeeded generations by the UTC day they completed, which is how Pixelforge bills them. Exact values on hover."
        />

        <form
            class="mb-8 flex flex-wrap items-end gap-3"
            @submit.prevent="apply"
        >
            <div class="grid gap-1.5">
                <Label for="start_date">From</Label>
                <Input
                    id="start_date"
                    v-model="range.start_date"
                    type="date"
                    :max="filters.max_date"
                />
            </div>
            <div class="grid gap-1.5">
                <Label for="end_date">To</Label>
                <Input
                    id="end_date"
                    v-model="range.end_date"
                    type="date"
                    :max="filters.max_date"
                />
            </div>
            <Button type="submit" variant="outline">Apply</Button>
            <p class="w-full text-xs text-muted-foreground">
                Up to 92 days, the most Pixelforge's usage report covers.
            </p>
        </form>

        <dl class="mb-10 grid grid-cols-2 gap-4 sm:grid-cols-4">
            <div class="rounded-lg border p-4">
                <dt class="text-sm text-muted-foreground">Credit spend</dt>
                <dd
                    class="text-xl font-semibold tabular-nums"
                    :title="`$${totals.spend.exact}`"
                >
                    {{ totals.spend.display }}
                </dd>
            </div>
            <div class="rounded-lg border p-4">
                <dt class="text-sm text-muted-foreground">Provider cost</dt>
                <dd
                    class="text-xl font-semibold tabular-nums"
                    :title="`$${totals.providerCost.exact}`"
                >
                    {{ totals.providerCost.display }}
                </dd>
            </div>
            <div class="rounded-lg border p-4">
                <dt class="text-sm text-muted-foreground">Profit</dt>
                <dd
                    class="text-xl font-semibold tabular-nums"
                    :title="`$${totals.profit.exact}`"
                >
                    {{ totals.profit.display }}
                </dd>
            </div>
            <div class="rounded-lg border p-4">
                <dt class="text-sm text-muted-foreground">
                    Matches Pixelforge
                </dt>
                <dd class="text-xl font-semibold">
                    <template v-if="totals.difference === null">—</template>
                    <template v-else-if="matches(totals)">Yes</template>
                    <template v-else>
                        Off by
                        <span class="tabular-nums"
                            >${{ totals.difference.exact }}</span
                        >
                    </template>
                </dd>
            </div>
        </dl>

        <p
            v-if="pixelforgeUsageError"
            class="mb-6 rounded-md border p-3 text-sm"
            role="alert"
        >
            {{ pixelforgeUsageError }} Our own figures are shown without
            reconciliation.
        </p>

        <section class="mb-10">
            <h2 class="mb-3 text-base font-medium">By day (UTC)</h2>
            <div class="overflow-x-auto rounded-lg border">
                <table class="w-full min-w-[44rem] text-sm">
                    <thead class="border-b text-muted-foreground">
                        <tr>
                            <th scope="col" class="p-3 text-left font-normal">
                                Day
                            </th>
                            <th scope="col" class="p-3 text-right font-normal">
                                Generations
                            </th>
                            <th scope="col" class="p-3 text-right font-normal">
                                Credit spend
                            </th>
                            <th scope="col" class="p-3 text-right font-normal">
                                Provider cost
                            </th>
                            <th scope="col" class="p-3 text-right font-normal">
                                Profit
                            </th>
                            <th scope="col" class="p-3 text-right font-normal">
                                Pixelforge says
                            </th>
                            <th scope="col" class="p-3 text-right font-normal">
                                Difference
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y tabular-nums">
                        <tr
                            v-for="day in days"
                            :key="day.date"
                            :class="{
                                'text-muted-foreground':
                                    day.generations === 0 &&
                                    day.pixelforgeCost?.exact === '0.000000000',
                            }"
                        >
                            <th scope="row" class="p-3 text-left font-normal">
                                {{ day.date }}
                            </th>
                            <td class="p-3 text-right">
                                {{ day.generations }}
                            </td>
                            <td class="p-3 text-right">
                                ${{ day.spend.exact }}
                            </td>
                            <td class="p-3 text-right">
                                ${{ day.providerCost.exact }}
                            </td>
                            <td class="p-3 text-right">
                                ${{ day.profit.exact }}
                            </td>
                            <td class="p-3 text-right">
                                {{
                                    day.pixelforgeCost
                                        ? `$${day.pixelforgeCost.exact}`
                                        : '—'
                                }}
                            </td>
                            <td
                                class="p-3 text-right"
                                :class="{
                                    'font-semibold underline':
                                        day.difference && !matches(day),
                                }"
                            >
                                <template v-if="day.difference === null"
                                    >—</template
                                >
                                <template v-else-if="matches(day)">
                                    Match
                                </template>
                                <template v-else>
                                    ${{ day.difference.exact }}
                                </template>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section>
            <h2 class="mb-3 text-base font-medium">By user</h2>
            <div class="overflow-x-auto rounded-lg border">
                <table class="w-full min-w-[36rem] text-sm">
                    <thead class="border-b text-muted-foreground">
                        <tr>
                            <th scope="col" class="p-3 text-left font-normal">
                                User
                            </th>
                            <th scope="col" class="p-3 text-right font-normal">
                                Generations
                            </th>
                            <th scope="col" class="p-3 text-right font-normal">
                                Credit spend
                            </th>
                            <th scope="col" class="p-3 text-right font-normal">
                                Provider cost
                            </th>
                            <th scope="col" class="p-3 text-right font-normal">
                                Profit
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y tabular-nums">
                        <tr v-for="user in users" :key="user.id">
                            <th scope="row" class="p-3 text-left font-normal">
                                <span class="block">{{ user.name }}</span>
                                <span class="block text-muted-foreground">{{
                                    user.email
                                }}</span>
                            </th>
                            <td class="p-3 text-right">
                                {{ user.generations }}
                            </td>
                            <td class="p-3 text-right">
                                ${{ user.spend.exact }}
                            </td>
                            <td class="p-3 text-right">
                                ${{ user.providerCost.exact }}
                            </td>
                            <td class="p-3 text-right">
                                ${{ user.profit.exact }}
                            </td>
                        </tr>
                        <tr v-if="!users.length">
                            <td
                                colspan="5"
                                class="p-6 text-center text-muted-foreground"
                            >
                                No succeeded generations in this range.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</template>
