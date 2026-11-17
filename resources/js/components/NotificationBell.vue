<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { Bell } from '@lucide/vue';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { formatDateTime } from '@/lib/dates';
import { index as generationsIndex } from '@/routes/generations';
import { read } from '@/routes/notifications';

const page = usePage();
const notifications = computed(() => page.props.notifications);
const unreadCount = computed(() => notifications.value?.unreadCount ?? 0);

/** Which items were unread when the menu was opened, so they stay marked while it's open. */
const unreadWhenOpened = ref(new Set<string>());

function onOpenChange(open: boolean): void {
    if (!open || unreadCount.value === 0) {
        return;
    }

    unreadWhenOpened.value = new Set(
        notifications.value?.recent
            .filter((notification) => !notification.read)
            .map(({ id }) => id),
    );

    router.post(
        read.url(),
        {},
        {
            preserveScroll: true,
            preserveState: true,
            only: ['notifications'],
        },
    );
}
</script>

<template>
    <DropdownMenu @update:open="onOpenChange">
        <DropdownMenuTrigger :as-child="true">
            <Button variant="ghost" size="icon" class="relative size-9">
                <Bell class="size-5" />
                <span
                    v-if="unreadCount > 0"
                    class="absolute top-1 right-1 flex h-4 min-w-4 items-center justify-center rounded-full bg-foreground px-1 text-[10px] leading-none font-semibold text-background tabular-nums"
                >
                    {{ unreadCount > 9 ? '9+' : unreadCount }}
                </span>
                <span class="sr-only">
                    Notifications{{
                        unreadCount > 0 ? `, ${unreadCount} unread` : ''
                    }}
                </span>
            </Button>
        </DropdownMenuTrigger>

        <DropdownMenuContent align="end" class="w-80 max-w-[calc(100vw-2rem)]">
            <DropdownMenuLabel>Notifications</DropdownMenuLabel>
            <DropdownMenuSeparator />

            <template v-if="notifications?.recent.length">
                <DropdownMenuItem
                    v-for="notification in notifications.recent"
                    :key="notification.id"
                    :as-child="true"
                >
                    <Link
                        :href="generationsIndex()"
                        class="flex cursor-pointer items-start gap-3 py-2"
                    >
                        <span
                            class="mt-1.5 size-2 shrink-0 rounded-full"
                            :class="
                                unreadWhenOpened.has(notification.id) ||
                                !notification.read
                                    ? 'bg-foreground'
                                    : 'bg-transparent'
                            "
                            aria-hidden="true"
                        />
                        <span class="grid min-w-0 gap-0.5 text-sm">
                            <span class="font-medium">
                                {{ notification.title }}
                            </span>
                            <span class="truncate text-muted-foreground">
                                {{ notification.prompt }}
                            </span>
                            <span class="text-muted-foreground">
                                {{ notification.detail }}
                            </span>
                            <span class="text-xs text-muted-foreground">
                                {{ formatDateTime(notification.createdAt) }}
                            </span>
                        </span>
                    </Link>
                </DropdownMenuItem>
            </template>
            <p
                v-else
                class="px-2 py-6 text-center text-sm text-muted-foreground"
            >
                No notifications yet. You'll hear here when a generation
                finishes.
            </p>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
