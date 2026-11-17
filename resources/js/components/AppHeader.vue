<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { Menu } from '@lucide/vue';
import { computed } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Sheet,
    SheetContent,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import UserMenuContent from '@/components/UserMenuContent.vue';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { getInitials } from '@/composables/useInitials';
import { index as creditsIndex } from '@/routes/credits';
import { create, index } from '@/routes/generations';
import type { BreadcrumbItem, NavItem } from '@/types';

type Props = {
    breadcrumbs?: BreadcrumbItem[];
};

const props = withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});

const page = usePage();
const auth = computed(() => page.props.auth);
const credits = computed(() => page.props.credits);
const balanceTitle = computed(() =>
    credits.value ? 'Balance: $' + credits.value.balance.exact : '',
);
const { isCurrentUrl } = useCurrentUrl();

const mainNavItems: NavItem[] = [
    {
        title: 'Generate',
        href: create(),
    },
    {
        title: 'History',
        href: index(),
    },
    {
        title: 'Credits',
        href: creditsIndex(),
    },
];
</script>

<template>
    <div>
        <header class="border-b">
            <div
                class="mx-auto flex h-14 max-w-5xl items-center gap-6 px-4 sm:px-6"
            >
                <Sheet>
                    <SheetTrigger :as-child="true">
                        <Button
                            variant="ghost"
                            size="icon"
                            class="-ml-2 size-9 md:hidden"
                        >
                            <Menu class="size-5" />
                            <span class="sr-only">Open navigation</span>
                        </Button>
                    </SheetTrigger>
                    <SheetContent side="left" class="w-72 p-6">
                        <SheetTitle class="sr-only">Navigation menu</SheetTitle>
                        <SheetHeader class="p-0 text-left">
                            <AppLogoIcon class="size-6 fill-current" />
                        </SheetHeader>
                        <nav class="-mx-3 flex flex-col gap-1">
                            <Link
                                v-for="item in mainNavItems"
                                :key="item.title"
                                :href="item.href"
                                class="rounded-md px-3 py-2 text-sm font-medium hover:bg-accent"
                                :class="{
                                    'bg-accent': isCurrentUrl(item.href),
                                }"
                            >
                                {{ item.title }}
                            </Link>
                        </nav>
                    </SheetContent>
                </Sheet>

                <Link :href="create()" class="flex items-center gap-2">
                    <AppLogo />
                </Link>

                <nav class="hidden h-full items-stretch gap-1 md:flex">
                    <Link
                        v-for="item in mainNavItems"
                        :key="item.title"
                        :href="item.href"
                        class="flex items-center border-b-2 px-3 text-sm font-medium"
                        :class="
                            isCurrentUrl(item.href)
                                ? 'border-foreground text-foreground'
                                : 'border-transparent text-muted-foreground hover:text-foreground'
                        "
                    >
                        {{ item.title }}
                    </Link>
                </nav>

                <div class="ml-auto flex items-center gap-2">
                    <Link
                        v-if="credits"
                        :href="creditsIndex()"
                        class="rounded-md border px-3 py-1.5 text-sm font-medium tabular-nums hover:bg-accent"
                        :title="balanceTitle"
                    >
                        {{ credits.balance.display }}
                        <span class="sr-only">credit balance</span>
                    </Link>
                    <DropdownMenu>
                        <DropdownMenuTrigger :as-child="true">
                            <Button
                                variant="ghost"
                                size="icon"
                                class="size-9 rounded-full p-0"
                            >
                                <Avatar
                                    class="size-8 overflow-hidden rounded-full"
                                >
                                    <AvatarImage
                                        v-if="auth.user.avatar"
                                        :src="auth.user.avatar"
                                        :alt="auth.user.name"
                                    />
                                    <AvatarFallback
                                        class="bg-muted text-xs font-semibold text-foreground"
                                    >
                                        {{ getInitials(auth.user?.name) }}
                                    </AvatarFallback>
                                </Avatar>
                                <span class="sr-only">Open user menu</span>
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" class="w-56">
                            <UserMenuContent :user="auth.user" />
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>
            </div>
        </header>

        <div v-if="props.breadcrumbs.length > 1" class="border-b">
            <div
                class="mx-auto flex h-11 max-w-5xl items-center px-4 text-muted-foreground sm:px-6"
            >
                <Breadcrumbs :breadcrumbs="breadcrumbs" />
            </div>
        </div>
    </div>
</template>
