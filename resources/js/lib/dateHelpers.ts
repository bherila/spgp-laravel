/**
 * Format a DATE-only field (no time component) as M/D/YYYY using UTC components.
 * Use for database DATE columns: passholder_birth_date, assign_code_date,
 * promo code start_date / expiration_date, etc.
 * Never show timezone — the value has no time part.
 */
export function formatDateOnly(dateStr: string | null | undefined): string {
  if (!dateStr) return '—';
  const d = new Date(dateStr);
  return `${d.getUTCMonth() + 1}/${d.getUTCDate()}/${d.getUTCFullYear()}`;
}

/**
 * Returns a human-readable countdown string (e.g. "2d 4h 37m") from now until
 * the given deadline, or null if the deadline has already passed.
 */
export function getCountdown(deadlineStr: string, now: Date): string | null {
  const diff = new Date(deadlineStr).getTime() - now.getTime();
  if (diff <= 0) return null;
  const days = Math.floor(diff / (1000 * 60 * 60 * 24));
  const hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
  const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
  const parts: string[] = [];
  if (days > 0) parts.push(`${days}d`);
  parts.push(`${hours}h ${minutes}m`);
  return parts.join(' ');
}

/** Milliseconds in 3 days — use to detect urgent deadlines. */
export const THREE_DAYS_MS = 3 * 24 * 60 * 60 * 1000;

/**
 * Format a DATETIME/TIMESTAMP field in the user's local timezone,
 * including the 3-letter timezone abbreviation (e.g. "Apr 18, 2026, 02:30 PM PDT").
 * Use for database DATETIME/TIMESTAMP columns: deadlines, created_at, etc.
 */
export function formatDateTime(dateStr: string | null | undefined): string {
  if (!dateStr) return '—';
  return new Date(dateStr).toLocaleString(undefined, {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
    timeZoneName: 'short',
  });
}
