import { router, usePage, usePoll } from '@inertiajs/vue3';
import { watch } from 'vue';
import { toast } from 'vue-sonner';
import { index as generationsIndex } from '@/routes/generations';
import type { AppNotification } from '@/types/notifications';

const POLL_INTERVAL_MS = 4000;

/**
 * Notifications already on screen or in the bell. Kept at module level so it
 * survives page visits; the first notifications the app sees are treated as
 * old news, so a page load never replays them as toasts.
 */
const seen = new Set<string>();
let seeded = false;

function show(notification: AppNotification): void {
    const options = {
        description: `${notification.prompt} · ${notification.detail}`,
        action: {
            label: 'View',
            onClick: () => router.visit(generationsIndex.url()),
        },
    };

    if (notification.status === 'failed') {
        toast.error(notification.title, options);
    } else {
        toast.success(notification.title, options);
    }
}

/**
 * Keeps the header's notifications (and balance) fresh while generations are
 * running, on any page, and shows each new one once as a toast.
 */
export function useGenerationNotifications(): void {
    const page = usePage();

    const { start, stop } = usePoll(
        POLL_INTERVAL_MS,
        { only: ['notifications', 'generationsInProgress', 'credits'] },
        { autoStart: false },
    );

    watch(
        () => page.props.generationsInProgress,
        (inProgress) => (inProgress ? start() : stop()),
        { immediate: true },
    );

    watch(
        () => page.props.notifications?.recent,
        (recent) => {
            if (!recent) {
                return;
            }

            if (!seeded) {
                recent.forEach(({ id }) => seen.add(id));
                seeded = true;

                return;
            }

            [...recent].reverse().forEach((notification) => {
                if (seen.has(notification.id)) {
                    return;
                }

                seen.add(notification.id);

                if (!notification.read) {
                    show(notification);
                }
            });
        },
        { immediate: true },
    );
}
