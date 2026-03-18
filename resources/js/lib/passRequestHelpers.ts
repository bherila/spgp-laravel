/**
 * Derive age group label from birth date string.
 * Age groups: Adult (23+), Young Adult (13-22), Child (5-12), 4 & under
 */
export function getAgeGroup(birthDateStr: string | null): string {
  if (!birthDateStr) return '';
  const today = new Date();
  const birth = new Date(birthDateStr);
  let age = today.getUTCFullYear() - birth.getUTCFullYear();
  const monthDiff = today.getUTCMonth() - birth.getUTCMonth();
  if (monthDiff < 0 || (monthDiff === 0 && today.getUTCDate() < birth.getUTCDate())) {
    age--;
  }
  if (age >= 23) return 'Adult (23+)';
  if (age >= 13) return 'Young Adult (13-22)';
  if (age >= 5) return 'Child (5-12)';
  return '4 & under';
}

/**
 * Format a date string as MM/DD/YYYY using UTC date components.
 * Intended for pasting into Excel / Google Sheets.
 * Returns empty string for null/undefined input.
 */
export function formatBirthDateUTC(dateStr: string | null): string {
  if (!dateStr) return '';
  const d = new Date(dateStr);
  const month = String(d.getUTCMonth() + 1).padStart(2, '0');
  const day = String(d.getUTCDate()).padStart(2, '0');
  const year = d.getUTCFullYear();
  return `${month}/${day}/${year}`;
}
