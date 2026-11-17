const dateTimeFormat = new Intl.DateTimeFormat(undefined, {
    dateStyle: 'medium',
    timeStyle: 'short',
});

export function formatDateTime(iso: string | null): string {
    return iso ? dateTimeFormat.format(new Date(iso)) : '';
}
