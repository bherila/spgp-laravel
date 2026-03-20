export interface PromoCode {
  promo_code: string;
  season_id: number;
  start_date: string;
  expiration_date: string;
  country: 'USA' | 'Canada' | null;
  is_suspended: boolean;
  is_assigned: boolean;
  created_at: string;
}

export interface PromoCodeListResponse {
  season: unknown;
  promo_codes: PromoCode[];
}

export interface ImportCodesResult {
  message: string;
  imported: number;
  skipped: number;
  errors: string[];
}

export async function fetchPromoCodes(seasonId: string): Promise<PromoCodeListResponse> {
  const response = await fetch(`/api/admin/seasons/${seasonId}/promo-codes/list`, {
    headers: { Accept: 'application/json' },
  });
  if (!response.ok) throw new Error('Failed to fetch promo codes');
  return response.json() as Promise<PromoCodeListResponse>;
}

export async function importCodes(
  seasonId: string,
  tsv: string,
  country: 'USA' | 'Canada',
  csrfToken: string,
): Promise<ImportCodesResult> {
  const response = await fetch(`/api/admin/seasons/${seasonId}/promo-codes/import`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      Accept: 'application/json',
      'X-CSRF-TOKEN': csrfToken,
    },
    body: JSON.stringify({ tsv, country }),
  });
  const data = (await response.json()) as ImportCodesResult & { message?: string };
  if (!response.ok) throw new Error(data.message ?? 'Import failed');
  return data;
}
