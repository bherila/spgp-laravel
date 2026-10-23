import { apiRequest } from '@/lib/apiClient';

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

export function fetchPromoCodes(seasonId: string): Promise<PromoCodeListResponse> {
  return apiRequest(`/api/admin/seasons/${seasonId}/promo-codes/list`, {
    errorMessage: 'Failed to fetch promo codes',
  });
}

export function importCodes(
  seasonId: string,
  tsv: string,
  country: 'USA' | 'Canada',
  csrfToken: string,
): Promise<ImportCodesResult> {
  return apiRequest(`/api/admin/seasons/${seasonId}/promo-codes/import`, {
    method: 'POST', csrfToken, allowEmpty: false,
    json: { tsv, country },
    errorMessage: 'Import failed',
  });
}
