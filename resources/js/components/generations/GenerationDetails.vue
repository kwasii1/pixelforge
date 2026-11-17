<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Download, RotateCcw } from '@lucide/vue';
import GenerationCost from '@/components/generations/GenerationCost.vue';
import GenerationImage from '@/components/generations/GenerationImage.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { formatDateTime } from '@/lib/dates';
import { create } from '@/routes/generations';
import type { Generation } from '@/types/generations';

defineProps<{
    generation: Generation | null;
}>();

const open = defineModel<boolean>('open', { required: true });
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent
            v-if="generation"
            class="max-h-[calc(100dvh-2rem)] overflow-y-auto sm:max-w-2xl"
        >
            <DialogHeader>
                <DialogTitle>{{ generation.statusLabel }}</DialogTitle>
                <DialogDescription>
                    {{ generation.sizeLabel }} ({{
                        generation.size.replace('x', ' × ')
                    }}) · {{ generation.qualityLabel }} quality ·
                    {{ formatDateTime(generation.createdAt) }}
                </DialogDescription>
            </DialogHeader>

            <GenerationImage :generation="generation" />

            <p class="text-sm whitespace-pre-wrap">{{ generation.prompt }}</p>

            <ul
                v-if="generation.referenceImages.length"
                class="grid gap-1 text-sm"
            >
                <li class="text-muted-foreground">Reference images</li>
                <li
                    v-for="url in generation.referenceImages"
                    :key="url"
                    class="truncate"
                >
                    {{ url }}
                </li>
            </ul>

            <p
                v-if="generation.failureReason"
                class="rounded-md border p-3 text-sm"
            >
                {{ generation.failureReason }} You were not charged.
            </p>

            <GenerationCost :generation="generation" />

            <table v-if="generation.costBreakdown" class="w-full text-sm">
                <caption class="pb-2 text-left font-medium">
                    How the price was worked out
                </caption>
                <thead class="text-muted-foreground">
                    <tr class="border-b">
                        <th scope="col" class="py-1.5 text-left font-normal">
                            Tokens
                        </th>
                        <th scope="col" class="py-1.5 text-right font-normal">
                            Count
                        </th>
                        <th
                            scope="col"
                            class="hidden py-1.5 text-right font-normal sm:table-cell"
                        >
                            Per 1M
                        </th>
                        <th scope="col" class="py-1.5 text-right font-normal">
                            Cost
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y tabular-nums">
                    <tr
                        v-for="line in generation.costBreakdown.lines"
                        :key="line.label"
                    >
                        <th scope="row" class="py-1.5 text-left font-normal">
                            {{ line.label }}
                        </th>
                        <td class="py-1.5 text-right">
                            {{ line.tokens.toLocaleString('en-US') }}
                        </td>
                        <td class="hidden py-1.5 text-right sm:table-cell">
                            ${{ line.ratePerMillion }}
                        </td>
                        <td class="py-1.5 text-right">
                            ${{ line.cost.exact }}
                        </td>
                    </tr>
                </tbody>
                <tfoot class="tabular-nums">
                    <tr class="border-t">
                        <th
                            scope="row"
                            colspan="2"
                            class="py-1.5 text-left font-normal sm:hidden"
                        >
                            Pixelforge cost
                        </th>
                        <th
                            scope="row"
                            colspan="3"
                            class="hidden py-1.5 text-left font-normal sm:table-cell"
                        >
                            Pixelforge cost
                        </th>
                        <td class="py-1.5 text-right">
                            ${{ generation.costBreakdown.providerCost.exact }}
                        </td>
                    </tr>
                    <tr>
                        <th
                            scope="row"
                            colspan="2"
                            class="py-1.5 text-left font-normal sm:hidden"
                        >
                            Service fee (25%)
                        </th>
                        <th
                            scope="row"
                            colspan="3"
                            class="hidden py-1.5 text-left font-normal sm:table-cell"
                        >
                            Service fee (25%)
                        </th>
                        <td class="py-1.5 text-right">
                            ${{ generation.costBreakdown.markup.exact }}
                        </td>
                    </tr>
                    <tr class="border-t font-semibold">
                        <th
                            scope="row"
                            colspan="2"
                            class="py-1.5 text-left sm:hidden"
                        >
                            Charged
                        </th>
                        <th
                            scope="row"
                            colspan="3"
                            class="hidden py-1.5 text-left sm:table-cell"
                        >
                            Charged
                        </th>
                        <td class="py-1.5 text-right">
                            ${{ generation.costBreakdown.price.exact }}
                        </td>
                    </tr>
                </tfoot>
            </table>

            <div class="flex flex-wrap gap-2">
                <Button
                    v-if="generation.imageUrl && !generation.imageExpired"
                    as-child
                    variant="outline"
                >
                    <a
                        :href="generation.imageUrl"
                        target="_blank"
                        rel="noopener noreferrer"
                    >
                        <Download />
                        Open full size
                    </a>
                </Button>
                <Button as-child variant="outline">
                    <Link :href="create({ query: { from: generation.id } })">
                        <RotateCcw />
                        {{
                            generation.status === 'failed'
                                ? 'Try again'
                                : 'Generate again'
                        }}
                    </Link>
                </Button>
            </div>
        </DialogContent>
    </Dialog>
</template>
