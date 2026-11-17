<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { Button } from '@/components/ui/button';
import { dashboard, login, register } from '@/routes';
</script>

<template>
    <Head title="Welcome" />

    <div class="flex min-h-screen flex-col bg-background text-foreground">
        <header
            class="mx-auto flex h-14 w-full max-w-5xl items-center justify-between px-4 sm:px-6"
        >
            <span class="flex items-center gap-2 text-sm font-semibold">
                <AppLogoIcon class="size-5 fill-current" />
                Pixelforge
            </span>
            <nav class="flex items-center gap-2 text-sm">
                <Button v-if="$page.props.auth.user" as-child size="sm">
                    <Link :href="dashboard()">Open app</Link>
                </Button>
                <template v-else>
                    <Button as-child variant="ghost" size="sm">
                        <Link :href="login()">Log in</Link>
                    </Button>
                    <Button as-child size="sm">
                        <Link :href="register()">Sign up</Link>
                    </Button>
                </template>
            </nav>
        </header>

        <main
            class="mx-auto flex w-full max-w-5xl flex-1 flex-col justify-center gap-6 px-4 py-16 sm:px-6"
        >
            <h1
                class="max-w-xl text-3xl font-semibold tracking-tight sm:text-4xl"
            >
                Generate images. Know what they cost.
            </h1>
            <p class="max-w-xl text-muted-foreground">
                Write a prompt, see the price range before you submit, and pay
                only for images that succeed.
            </p>
            <div>
                <Button v-if="$page.props.auth.user" as-child>
                    <Link :href="dashboard()">Open app</Link>
                </Button>
                <Button v-else as-child>
                    <Link :href="register()">Get started</Link>
                </Button>
            </div>
        </main>
    </div>
</template>
