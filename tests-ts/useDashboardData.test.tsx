import { act, renderHook } from '@testing-library/react';

import { useDashboardData } from '@/hooks/useDashboardData';
import { readPageData } from '@/lib/pageData';

describe('embedded dashboard data', () => {
  beforeEach(() => {
    document.body.innerHTML = '<script id="dashboard-data" type="application/json">[{"id":1}]</script>';
    globalThis.fetch = jest.fn();
  });

  afterEach(() => {
    document.body.innerHTML = '';
    jest.restoreAllMocks();
  });

  it('renders embedded data immediately without a mount request', () => {
    const { result } = renderHook(() => useDashboardData());
    expect(result.current.seasons).toEqual([{ id: 1 }]);
    expect(result.current.loading).toBe(false);
    expect(fetch).not.toHaveBeenCalled();
  });

  it('keeps the current data after a failed refresh and can recover', async () => {
    const fetchMock = jest.mocked(fetch);
    fetchMock.mockResolvedValueOnce({ ok: false } as Response);
    const { result } = renderHook(() => useDashboardData());
    await act(() => result.current.refresh());
    expect(result.current.seasons).toEqual([{ id: 1 }]);
    expect(result.current.error).toBe('Failed to refresh dashboard data');

    fetchMock.mockResolvedValueOnce({ ok: true, json: async () => [{ id: 2 }] } as Response);
    await act(() => result.current.refresh());
    expect(result.current.seasons).toEqual([{ id: 2 }]);
    expect(result.current.error).toBeNull();
    expect(result.current.loading).toBe(false);
  });

  it('reports missing or malformed bootstrap data instead of showing an empty dashboard', () => {
    expect(() => readPageData('missing')).toThrow('Missing page data');
    document.getElementById('dashboard-data')!.textContent = '{broken';
    expect(() => readPageData('dashboard-data')).toThrow(SyntaxError);
  });
});
