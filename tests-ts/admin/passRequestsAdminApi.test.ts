import {
  assignCodes,
  autoAssign,
  bulkDeletePassRequests,
  clearCodes,
  fetchPassRequests,
  fetchRepoCount,
  sendEmail,
} from '@/admin/passRequestsAdminApi';

describe('passRequestsAdminApi', () => {
  beforeEach(() => {
    globalThis.fetch = jest.fn();
  });

  afterEach(() => {
    jest.resetAllMocks();
  });

  describe('fetchPassRequests', () => {
    it('returns season and pass_requests on success', async () => {
      const payload = { season: { id: 1 }, pass_requests: [{ id: 'abc' }] };
      (globalThis.fetch as jest.Mock).mockResolvedValue({
        ok: true,
        json: async () => payload,
      });

      const result = await fetchPassRequests('1', false);
      expect(result).toEqual(payload);
      expect(globalThis.fetch).toHaveBeenCalledWith(
        '/api/admin/seasons/1/pass-requests/list?',
        expect.objectContaining({ headers: { Accept: 'application/json' } }),
      );
    });

    it('appends recent_only=true when showRecentOnly is true', async () => {
      (globalThis.fetch as jest.Mock).mockResolvedValue({
        ok: true,
        json: async () => ({ season: {}, pass_requests: [] }),
      });
      await fetchPassRequests('2', true);
      const url = (globalThis.fetch as jest.Mock).mock.calls[0][0] as string;
      expect(url).toContain('recent_only=true');
    });

    it('throws when response is not ok', async () => {
      (globalThis.fetch as jest.Mock).mockResolvedValue({ ok: false, json: async () => ({}) });
      await expect(fetchPassRequests('1', false)).rejects.toThrow('Failed to fetch data');
    });
  });

  describe('fetchRepoCount', () => {
    it('returns count of non-suspended codes', async () => {
      (globalThis.fetch as jest.Mock).mockResolvedValue({
        ok: true,
        json: async () => ({
          promo_codes: [
            { is_suspended: false },
            { is_suspended: true },
            { is_suspended: false },
          ],
        }),
      });
      const count = await fetchRepoCount('1');
      expect(count).toBe(2);
    });

    it('returns 0 when response is not ok', async () => {
      (globalThis.fetch as jest.Mock).mockResolvedValue({ ok: false });
      const count = await fetchRepoCount('1');
      expect(count).toBe(0);
    });
  });

  describe('assignCodes', () => {
    it('returns message on success', async () => {
      (globalThis.fetch as jest.Mock).mockResolvedValue({
        ok: true,
        json: async () => ({ message: 'Assigned 2 codes.' }),
      });
      const result = await assignCodes('1', ['r1', 'r2'], 'CODE1\nCODE2', 'csrf');
      expect(result.message).toBe('Assigned 2 codes.');
    });

    it('throws with server message on failure', async () => {
      (globalThis.fetch as jest.Mock).mockResolvedValue({
        ok: false,
        json: async () => ({ message: 'Not enough codes' }),
      });
      await expect(assignCodes('1', ['r1'], 'CODE1', 'csrf')).rejects.toThrow('Not enough codes');
    });
  });

  describe('clearCodes', () => {
    it('returns message on success', async () => {
      (globalThis.fetch as jest.Mock).mockResolvedValue({
        ok: true,
        json: async () => ({ message: 'Cleared 1 pass request.' }),
      });
      const result = await clearCodes('1', ['r1'], 'csrf');
      expect(result.message).toBe('Cleared 1 pass request.');
    });

    it('throws on failure', async () => {
      (globalThis.fetch as jest.Mock).mockResolvedValue({
        ok: false,
        json: async () => ({ message: 'Season not found' }),
      });
      await expect(clearCodes('1', ['r1'], 'csrf')).rejects.toThrow('Season not found');
    });
  });

  describe('sendEmail', () => {
    it('returns sent count on success', async () => {
      (globalThis.fetch as jest.Mock).mockResolvedValue({
        ok: true,
        json: async () => ({ sent: 1 }),
      });
      const result = await sendEmail('1', 'r1', false, 'csrf');
      expect(result.sent).toBe(1);
    });

    it('throws when response is not ok', async () => {
      (globalThis.fetch as jest.Mock).mockResolvedValue({ ok: false, json: async () => ({}) });
      await expect(sendEmail('1', 'r1', false, 'csrf')).rejects.toThrow('Failed to send email');
    });
  });

  describe('autoAssign', () => {
    it('returns message on success', async () => {
      (globalThis.fetch as jest.Mock).mockResolvedValue({
        ok: true,
        json: async () => ({ message: 'Auto-assigned 3 promo code(s).' }),
      });
      const result = await autoAssign('1', 'csrf');
      expect(result.message).toContain('Auto-assigned');
    });

    it('throws on failure', async () => {
      (globalThis.fetch as jest.Mock).mockResolvedValue({
        ok: false,
        json: async () => ({ message: 'No available promo codes in repository.' }),
      });
      await expect(autoAssign('1', 'csrf')).rejects.toThrow('No available promo codes in repository.');
    });
  });

  describe('bulkDeletePassRequests', () => {
    it('returns message on success', async () => {
      (globalThis.fetch as jest.Mock).mockResolvedValue({
        ok: true,
        json: async () => ({ message: 'Deleted 2 pass request(s).' }),
      });
      const result = await bulkDeletePassRequests('1', ['r1', 'r2'], 'csrf');
      expect(result.message).toContain('Deleted');
    });

    it('throws with server message when a request has a promo code', async () => {
      (globalThis.fetch as jest.Mock).mockResolvedValue({
        ok: false,
        json: async () => ({
          message: 'Cannot delete pass requests that have a promo code assigned.',
        }),
      });
      await expect(bulkDeletePassRequests('1', ['r1'], 'csrf')).rejects.toThrow(
        'Cannot delete pass requests that have a promo code assigned.',
      );
    });
  });
});
