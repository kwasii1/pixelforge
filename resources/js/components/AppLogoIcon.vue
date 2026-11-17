<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

type Props = {
    className?: HTMLAttributes['class'];
};

defineProps<Props>();

/**
 * The Pixelforge mark: a "P" drawn on a 3 × 5 pixel grid (4-unit pixels, 1-unit gaps).
 * public/favicon.svg uses the same cells on a rounded tile.
 */
const PIXELS = [
    [1, 1, 1],
    [1, 0, 1],
    [1, 1, 1],
    [1, 0, 0],
    [1, 0, 0],
];

const cells = PIXELS.flatMap((row, rowIndex) =>
    row.flatMap((filled, columnIndex) =>
        filled ? [{ x: 7 + columnIndex * 5, y: 2 + rowIndex * 5 }] : [],
    ),
);
</script>

<template>
    <svg
        xmlns="http://www.w3.org/2000/svg"
        viewBox="0 0 28 28"
        :class="className"
        v-bind="$attrs"
    >
        <rect
            v-for="cell in cells"
            :key="`${cell.x}-${cell.y}`"
            :x="cell.x"
            :y="cell.y"
            width="4"
            height="4"
            fill="currentColor"
        />
    </svg>
</template>
