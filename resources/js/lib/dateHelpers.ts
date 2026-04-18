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
