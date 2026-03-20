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
}

export interface RepoCode {
  is_suspended: boolean;
}

export async function fetchPassRequests(
  seasonId: string,
  showRecentOnly: boolean,
): Promise<PassRequestsListResponse> {
  const params = new URLSearchParams();
  if (showRecentOnly) params.set('recent_only', 'true');
  const response = await fetch(`/api/admin/seasons/${seasonId}/pass-requests/list?${params}`, {
    headers: { Accept: 'application/json' },
  });
  if (!response.ok) throw new Error('Failed to fetch data');
  return response.json() as Promise<PassRequestsListResponse>;
}

export async function fetchRepoCount(seasonId: string): Promise<number> {
  const response = await fetch(`/api/admin/seasons/${seasonId}/promo-codes/list`, {
    headers: { Accept: 'application/json' },
  });
  if (!response.ok) return 0;
  const data = (await response.json()) as { promo_codes?: RepoCode[] };
  return (data.promo_codes ?? []).filter((c) => !c.is_suspended).length;
}

export async function assignCodes(
  seasonId: string,
  passRequestIds: string[],
  codes: string,
  csrfToken: string,
): Promise<{ message: string }> {
  const response = await fetch(`/api/admin/seasons/${seasonId}/pass-requests/assign-codes`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      Accept: 'application/json',
      'X-CSRF-TOKEN': csrfToken,
    },
    body: JSON.stringify({ pass_request_ids: passRequestIds, codes }),
  });
  const data = (await response.json()) as { message?: string };
  if (!response.ok) throw new Error(data.message ?? 'Failed to assign codes');
  return data as { message: string };
}

export async function clearCodes(
  seasonId: string,
  passRequestIds: string[],
  csrfToken: string,
): Promise<{ message: string }> {
  const response = await fetch(`/api/admin/seasons/${seasonId}/pass-requests/clear-codes`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      Accept: 'application/json',
      'X-CSRF-TOKEN': csrfToken,
    },
    body: JSON.stringify({ pass_request_ids: passRequestIds }),
  });
  const data = (await response.json()) as { message?: string };
  if (!response.ok) throw new Error(data.message ?? 'Failed to unassign codes');
  return data as { message: string };
}

export async function sendEmail(
  seasonId: string,
  passRequestId: string,
  forceSend: boolean,
  csrfToken: string,
): Promise<{ sent?: number }> {
  const response = await fetch(`/api/admin/seasons/${seasonId}/pass-requests/send-emails`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      Accept: 'application/json',
      'X-CSRF-TOKEN': csrfToken,
    },
    body: JSON.stringify({ pass_request_ids: [passRequestId], force_send: forceSend }),
  });
  if (!response.ok) throw new Error('Failed to send email');
  return response.json() as Promise<{ sent?: number }>;
}

export async function autoAssign(
  seasonId: string,
  csrfToken: string,
): Promise<{ message: string }> {
  const response = await fetch(`/api/admin/seasons/${seasonId}/promo-codes/auto-assign`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      Accept: 'application/json',
      'X-CSRF-TOKEN': csrfToken,
    },
    body: JSON.stringify({}),
  });
  const data = (await response.json()) as { message?: string };
  if (!response.ok) throw new Error(data.message ?? 'Failed to auto-assign codes');
  return data as { message: string };
}

export async function bulkDeletePassRequests(
  seasonId: string,
  passRequestIds: string[],
  csrfToken: string,
): Promise<{ message: string }> {
  const response = await fetch(`/api/admin/seasons/${seasonId}/pass-requests/bulk-delete`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      Accept: 'application/json',
      'X-CSRF-TOKEN': csrfToken,
    },
    body: JSON.stringify({ pass_request_ids: passRequestIds }),
  });
  const data = (await response.json()) as { message?: string };
  if (!response.ok) throw new Error(data.message ?? 'Failed to delete pass requests');
  return data as { message: string };
}
