<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import AppLogoTile from '@/components/AppLogoTile.vue';

const name = usePage().props.name;
</script>

<template>
    <AppLogoTile class="size-8" />
    <span class="ml-1 truncate text-sm font-semibold">{{ name }}</span>
</template>
