export function formatDate(dateStr: string): string {
  if (!dateStr) return '—';
  return new Date(dateStr).toLocaleDateString();
}

export function formatDateTimeLocal(dateStr: string): string {
  const date = new Date(dateStr);
  return date.toISOString().slice(0, 16);
}
