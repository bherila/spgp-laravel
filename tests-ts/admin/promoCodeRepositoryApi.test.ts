import { fetchPromoCodes, importCodes } from '@/admin/promoCodeRepositoryApi';

describe('promoCodeRepositoryApi', () => {
  beforeEach(() => {
    globalThis.fetch = jest.fn();
  });

  afterEach(() => {
    jest.resetAllMocks();
  });

  describe('fetchPromoCodes', () => {
    it('returns promo_codes list on success', async () => {
      const payload = {
        season: { id: 1 },
        promo_codes: [{ promo_code: 'CODE1', country: 'USA', is_suspended: false, is_assigned: false }],
      };
      (globalThis.fetch as jest.Mock).mockResolvedValue({
        ok: true,
        json: async () => payload,
      });

      const result = await fetchPromoCodes('1');
      expect(result.promo_codes).toHaveLength(1);
      expect(result.promo_codes[0]?.promo_code).toBe('CODE1');
    });

    it('throws when response is not ok', async () => {
      (globalThis.fetch as jest.Mock).mockResolvedValue({ ok: false, json: async () => ({}) });
      await expect(fetchPromoCodes('1')).rejects.toThrow('Failed to fetch promo codes');
    });

    it('calls the correct URL', async () => {
      (globalThis.fetch as jest.Mock).mockResolvedValue({
        ok: true,
        json: async () => ({ season: {}, promo_codes: [] }),
      });
      await fetchPromoCodes('42');
      expect(globalThis.fetch).toHaveBeenCalledWith(
        '/api/admin/seasons/42/promo-codes/list',
        expect.objectContaining({ headers: { Accept: 'application/json' } }),
      );
    });
  });

  describe('importCodes', () => {
    it('returns import result on success', async () => {
      const payload = { message: 'Imported 3 promo code(s).', imported: 3, skipped: 0, errors: [] };
      (globalThis.fetch as jest.Mock).mockResolvedValue({
        ok: true,
        json: async () => payload,
      });

      const result = await importCodes('1', 'CODE1\nCODE2\nCODE3', 'USA', 'csrf');
      expect(result.imported).toBe(3);
      expect(result.errors).toHaveLength(0);
    });

    it('sends correct body to the API', async () => {
      (globalThis.fetch as jest.Mock).mockResolvedValue({
        ok: true,
        json: async () => ({ message: 'ok', imported: 1, skipped: 0, errors: [] }),
      });

      await importCodes('5', 'MYCODE', 'Canada', 'my-csrf');
      const [url, options] = (globalThis.fetch as jest.Mock).mock.calls[0] as [string, RequestInit];
      expect(url).toBe('/api/admin/seasons/5/promo-codes/import');
      expect(options.method).toBe('POST');
      const body = JSON.parse(options.body as string) as { tsv: string; country: string };
      expect(body.tsv).toBe('MYCODE');
      expect(body.country).toBe('Canada');
    });

    it('throws with server message on failure', async () => {
      (globalThis.fetch as jest.Mock).mockResolvedValue({
        ok: false,
        json: async () => ({ message: 'Invalid TSV data' }),
      });
      await expect(importCodes('1', 'bad data', 'USA', 'csrf')).rejects.toThrow('Invalid TSV data');
    });
  });
});
