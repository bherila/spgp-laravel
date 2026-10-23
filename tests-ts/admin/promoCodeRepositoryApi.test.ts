import { fetchPromoCodes, importCodes } from '@/admin/promoCodeRepositoryApi';

import { mockJsonResponse } from '../helpers/http';

describe('promoCodeRepositoryApi', () => {
  beforeEach(() => { globalThis.fetch = jest.fn(); });
  afterEach(() => { jest.resetAllMocks(); });

  it('returns promo codes', async () => {
    const payload = { season: { id: 1 }, promo_codes: [{ promo_code: 'CODE1', country: 'USA', is_suspended: false, is_assigned: false }] };
    (globalThis.fetch as jest.Mock).mockResolvedValue(mockJsonResponse(payload));
    expect((await fetchPromoCodes('1')).promo_codes[0]?.promo_code).toBe('CODE1');
  });

  it('provides context when a repository refresh fails', async () => {
    (globalThis.fetch as jest.Mock).mockResolvedValue(mockJsonResponse({}, 500));
    await expect(fetchPromoCodes('1')).rejects.toThrow('Failed to fetch promo codes');
  });

  it('requests the selected season repository', async () => {
    (globalThis.fetch as jest.Mock).mockResolvedValue(mockJsonResponse({ season: {}, promo_codes: [] }));
    await fetchPromoCodes('42');
    expect(globalThis.fetch).toHaveBeenCalledWith('/api/admin/seasons/42/promo-codes/list', expect.objectContaining({ headers: expect.any(Headers) }));
  });

  it('returns import counts and warnings', async () => {
    const payload = { message: 'Imported 3 promo code(s).', imported: 3, skipped: 0, errors: [] };
    (globalThis.fetch as jest.Mock).mockResolvedValue(mockJsonResponse(payload));
    await expect(importCodes('1', 'CODE1\nCODE2\nCODE3', 'USA', 'csrf')).resolves.toEqual(payload);
  });

  it('sends the pasted codes and selected country', async () => {
    (globalThis.fetch as jest.Mock).mockResolvedValue(mockJsonResponse({ message: 'ok', imported: 1, skipped: 0, errors: [] }));
    await importCodes('5', 'MYCODE', 'Canada', 'my-csrf');
    const [url, options] = (globalThis.fetch as jest.Mock).mock.calls[0] as [string, RequestInit];
    expect(url).toBe('/api/admin/seasons/5/promo-codes/import');
    expect(options.method).toBe('POST');
    expect(JSON.parse(options.body as string)).toEqual({ tsv: 'MYCODE', country: 'Canada' });
    expect(new Headers(options.headers).get('X-CSRF-TOKEN')).toBe('my-csrf');
  });

  it('preserves import validation messages', async () => {
    (globalThis.fetch as jest.Mock).mockResolvedValue(mockJsonResponse({ message: 'Invalid TSV data' }, 422));
    await expect(importCodes('1', 'bad data', 'USA', 'csrf')).rejects.toThrow('Invalid TSV data');
  });
});
