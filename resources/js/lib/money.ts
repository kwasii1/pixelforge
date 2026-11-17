const DECIMAL_PLACES = 9;

/**
 * Format integer nanodollars for people, e.g. "$0.0302", rounding half up.
 * Mirrors App\Support\Money::format so the browser and server agree.
 */
export function formatNanodollars(nanodollars: number, decimals = 4): string {
    const unit = 10 ** (DECIMAL_PLACES - decimals);
    const absolute = Math.abs(nanodollars);
    const rounded = Math.floor((absolute + Math.floor(unit / 2)) / unit);
    const sign = nanodollars < 0 ? '-' : '';

    if (rounded === 0 && absolute > 0) {
        return `${sign}<$0.${'0'.repeat(decimals - 1)}1`;
    }

    const scale = 10 ** decimals;
    const whole = Math.floor(rounded / scale).toLocaleString('en-US');
    const fraction = String(rounded % scale).padStart(decimals, '0');

    return `${sign}$${whole}.${fraction}`;
}
