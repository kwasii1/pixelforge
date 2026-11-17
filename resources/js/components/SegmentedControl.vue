<script setup lang="ts">
import type { Option } from '@/types/generations';

defineProps<{
    options: Option[];
    label: string;
    name: string;
}>();

const model = defineModel<string>({ required: true });
</script>

<template>
    <div
        role="radiogroup"
        :aria-label="label"
        class="grid auto-cols-fr grid-flow-col gap-1 rounded-md border p-1"
    >
        <label
            v-for="option in options"
            :key="option.value"
            class="flex cursor-pointer items-center justify-center gap-2 rounded px-3 py-2 text-sm font-medium has-focus-visible:ring-2 has-focus-visible:ring-ring"
            :class="
                model === option.value
                    ? 'bg-primary text-primary-foreground'
                    : 'text-muted-foreground hover:bg-accent hover:text-foreground'
            "
        >
            <input
                v-model="model"
                type="radio"
                class="sr-only"
                :name="name"
                :value="option.value"
            />
            <slot
                name="option"
                :option="option"
                :selected="model === option.value"
            >
                {{ option.label }}
            </slot>
        </label>
    </div>
</template>
