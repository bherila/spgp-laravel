export function formatDate(dateStr: string): string {
  if (!dateStr) return '—';
  const date = new Date(dateStr);
  return date.toLocaleString(undefined, { 
    year: 'numeric', 
    month: 'short', 
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
    timeZoneName: 'short'
  });
}

/**
 * Formats a date string for use in a datetime-local input (YYYY-MM-DDTHH:mm)
 * in the user's local timezone.
 */
export function formatDateTimeLocal(dateStr: string): string {
  if (!dateStr) return '';
  const date = new Date(dateStr);
  
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, '0');
  const day = String(date.getDate()).padStart(2, '0');
  const hours = String(date.getHours()).padStart(2, '0');
  const minutes = String(date.getMinutes()).padStart(2, '0');
  
  return `${year}-${month}-${day}T${hours}:${minutes}`;
}

/**
 * Converts a local datetime-local value to a UTC ISO string for the server.
 */
export function toIsoString(localDateTimeStr: string): string {
  if (!localDateTimeStr) return '';
  const date = new Date(localDateTimeStr);
  return date.toISOString();
}