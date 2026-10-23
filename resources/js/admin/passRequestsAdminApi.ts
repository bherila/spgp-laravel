import { apiRequest } from '@/lib/apiClient';

export interface PassRequestUser {
  id: number;
  name: string;
  email: string;
}

export interface SeasonPassType {
  id: number;
  pass_type_name: string;
}

export interface PassRequest {
  id: string;
  user_id: number;
  user: PassRequestUser;
  passholder_email: string;
  pass_type: string;
  season_pass_type: SeasonPassType | null;
  passholder_first_name: string;
  passholder_last_name: string;
  passholder_birth_date: string;
  is_renewal: boolean;
  renewal_pass_id: string | null;
  renewal_order_number: string | null;
  promo_code: string | null;
  country: string | null;
  assign_code_date: string | null;
  email_notify_time: string | null;
  created_at: string;
}

export interface Season {
  id: number;
  pass_name: string;
  pass_year: number;
  spreadsheet_url: string | null;
}

export interface PassRequestsListResponse {
  season: Season;
  pass_requests: PassRequest[];
  repo_count: number;
}

export function fetchPassRequests(
  seasonId: string,
  showRecentOnly: boolean,
): Promise<PassRequestsListResponse> {
  const params = new URLSearchParams();
  if (showRecentOnly) params.set('recent_only', 'true');
  return apiRequest<PassRequestsListResponse>(`/api/admin/seasons/${seasonId}/pass-requests/list?${params}`, {
    errorMessage: 'Failed to fetch data',
  });
}

export function assignCodes(
  seasonId: string,
  passRequestIds: string[],
  codes: string,
  csrfToken: string,
): Promise<{ message: string }> {
  return apiRequest(`/api/admin/seasons/${seasonId}/pass-requests/assign-codes`, {
    method: 'POST', csrfToken, allowEmpty: false,
    json: { pass_request_ids: passRequestIds, codes },
    errorMessage: 'Failed to assign codes',
  });
}

export function clearCodes(
  seasonId: string,
  passRequestIds: string[],
  csrfToken: string,
): Promise<{ message: string }> {
  return apiRequest(`/api/admin/seasons/${seasonId}/pass-requests/clear-codes`, {
    method: 'POST', csrfToken, allowEmpty: false,
    json: { pass_request_ids: passRequestIds },
    errorMessage: 'Failed to unassign codes',
  });
}

export function sendEmail(
  seasonId: string,
  passRequestId: string,
  forceSend: boolean,
  csrfToken: string,
): Promise<{ sent?: number }> {
  return apiRequest(`/api/admin/seasons/${seasonId}/pass-requests/send-emails`, {
    method: 'POST', csrfToken, allowEmpty: false,
    json: { pass_request_ids: [passRequestId], force_send: forceSend },
    errorMessage: 'Failed to send email',
  });
}

export function autoAssign(
  seasonId: string,
  csrfToken: string,
): Promise<{ message: string }> {
  return apiRequest(`/api/admin/seasons/${seasonId}/promo-codes/auto-assign`, {
    method: 'POST', csrfToken, allowEmpty: false, json: {},
    errorMessage: 'Failed to auto-assign codes',
  });
}

export function bulkDeletePassRequests(
  seasonId: string,
  passRequestIds: string[],
  csrfToken: string,
): Promise<{ message: string }> {
  return apiRequest(`/api/admin/seasons/${seasonId}/pass-requests/bulk-delete`, {
    method: 'POST', csrfToken, allowEmpty: false,
    json: { pass_request_ids: passRequestIds },
    errorMessage: 'Failed to delete pass requests',
  });
}
