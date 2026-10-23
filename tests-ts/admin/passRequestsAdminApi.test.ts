import {
  assignCodes,
  autoAssign,
  bulkDeletePassRequests,
  clearCodes,
  fetchPassRequests,
  sendEmail,
} from '@/admin/passRequestsAdminApi';

import { mockJsonResponse, mockTextResponse } from '../helpers/http';

describe('passRequestsAdminApi', () => {
  beforeEach(() => { globalThis.fetch = jest.fn(); });
  afterEach(() => { jest.resetAllMocks(); });

  it('fetches the season, pass requests, and available count in one request', async () => {
    const payload = { season: { id: 1 }, pass_requests: [{ id: 'abc' }], repo_count: 2 };
    (globalThis.fetch as jest.Mock).mockResolvedValue(mockJsonResponse(payload));
    await expect(fetchPassRequests('1', false)).resolves.toEqual(payload);
    expect(globalThis.fetch).toHaveBeenCalledWith(
      '/api/admin/seasons/1/pass-requests/list?', expect.objectContaining({ headers: expect.any(Headers) }),
    );
    expect(globalThis.fetch).toHaveBeenCalledTimes(1);
  });

  it('appends recent_only=true for the recent filter', async () => {
    (globalThis.fetch as jest.Mock).mockResolvedValue(mockJsonResponse({ season: {}, pass_requests: [], repo_count: 0 }));
    await fetchPassRequests('2', true);
    expect((globalThis.fetch as jest.Mock).mock.calls[0][0]).toContain('recent_only=true');
  });

  it('provides context when a list refresh fails', async () => {
    (globalThis.fetch as jest.Mock).mockResolvedValue(mockJsonResponse({}, 500));
    await expect(fetchPassRequests('1', false)).rejects.toThrow('Failed to fetch data');
  });

  it('assigns codes using the selected request IDs and explicit token', async () => {
    (globalThis.fetch as jest.Mock).mockResolvedValue(mockJsonResponse({ message: 'Assigned 2 codes.' }));
    await expect(assignCodes('1', ['r1', 'r2'], 'CODE1\nCODE2', 'csrf')).resolves.toEqual({ message: 'Assigned 2 codes.' });
    const options = (globalThis.fetch as jest.Mock).mock.calls[0][1] as RequestInit;
    expect(JSON.parse(options.body as string)).toEqual({ pass_request_ids: ['r1', 'r2'], codes: 'CODE1\nCODE2' });
    expect(new Headers(options.headers).get('X-CSRF-TOKEN')).toBe('csrf');
  });

  it('preserves assignment validation messages', async () => {
    (globalThis.fetch as jest.Mock).mockResolvedValue(mockJsonResponse({ message: 'Not enough codes' }, 422));
    await expect(assignCodes('1', ['r1'], 'CODE1', 'csrf')).rejects.toThrow('Not enough codes');
  });

  it('clears selected codes', async () => {
    (globalThis.fetch as jest.Mock).mockResolvedValue(mockJsonResponse({ message: 'Cleared 1 pass request.' }));
    await expect(clearCodes('1', ['r1'], 'csrf')).resolves.toEqual({ message: 'Cleared 1 pass request.' });
  });

  it('preserves a clear-code failure message', async () => {
    (globalThis.fetch as jest.Mock).mockResolvedValue(mockJsonResponse({ message: 'Season not found' }, 404));
    await expect(clearCodes('1', ['r1'], 'csrf')).rejects.toThrow('Season not found');
  });

  it('sends one selected email without retrying', async () => {
    (globalThis.fetch as jest.Mock).mockResolvedValue(mockJsonResponse({ sent: 1 }));
    await expect(sendEmail('1', 'r1', false, 'csrf')).resolves.toEqual({ sent: 1 });
    const options = (globalThis.fetch as jest.Mock).mock.calls[0][1] as RequestInit;
    expect(JSON.parse(options.body as string)).toEqual({ pass_request_ids: ['r1'], force_send: false });
    expect(globalThis.fetch).toHaveBeenCalledTimes(1);
  });

  it.each([200, 204])('rejects an empty accepted HTTP %s email result without repeating delivery', async (status) => {
    (globalThis.fetch as jest.Mock).mockResolvedValue(mockTextResponse('', status));
    await expect(sendEmail('1', 'r1', false, 'csrf')).rejects.toThrow('Refresh to check the result before submitting again.');
    expect(globalThis.fetch).toHaveBeenCalledTimes(1);
  });

  it('provides context when email sending fails', async () => {
    (globalThis.fetch as jest.Mock).mockResolvedValue(mockJsonResponse({}, 500));
    await expect(sendEmail('1', 'r1', false, 'csrf')).rejects.toThrow('Failed to send email');
    expect(globalThis.fetch).toHaveBeenCalledTimes(1);
  });

  it('returns automatic assignment results', async () => {
    (globalThis.fetch as jest.Mock).mockResolvedValue(mockJsonResponse({ message: 'Auto-assigned 3 promo code(s).' }));
    expect((await autoAssign('1', 'csrf')).message).toContain('Auto-assigned');
  });

  it('preserves automatic assignment failures', async () => {
    (globalThis.fetch as jest.Mock).mockResolvedValue(mockJsonResponse({ message: 'No available promo codes in repository.' }, 422));
    await expect(autoAssign('1', 'csrf')).rejects.toThrow('No available promo codes in repository.');
  });

  it('deletes selected pass requests', async () => {
    (globalThis.fetch as jest.Mock).mockResolvedValue(mockJsonResponse({ message: 'Deleted 2 pass request(s).' }));
    expect((await bulkDeletePassRequests('1', ['r1', 'r2'], 'csrf')).message).toContain('Deleted');
  });

  it('preserves the assigned-code deletion guard', async () => {
    (globalThis.fetch as jest.Mock).mockResolvedValue(mockJsonResponse({ message: 'Cannot delete pass requests that have a promo code assigned.' }, 422));
    await expect(bulkDeletePassRequests('1', ['r1'], 'csrf')).rejects.toThrow('Cannot delete pass requests that have a promo code assigned.');
  });
});
