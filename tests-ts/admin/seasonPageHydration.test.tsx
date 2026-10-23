import { act, fireEvent, screen } from '@testing-library/react';

import { mockJsonResponse } from '../helpers/http';

jest.mock('@/bootstrap', () => ({}));

function setupPage(mountId: string, data: unknown) {
  document.body.innerHTML = `<div id="${mountId}" data-season-id="1" data-season-name="Winter" data-season-year="2026"></div>`;
  const payload = document.createElement('script');
  payload.id = 'page-data';
  payload.type = 'application/json';
  payload.textContent = JSON.stringify(data);
  document.body.appendChild(payload);
  globalThis.fetch = jest.fn();
}

it('shows embedded promo codes immediately without fetching', async () => {
  setupPage('admin-promo-code-repository', {
    season: { id: 1 },
    promo_codes: [{
      promo_code: 'LEGACY-USA',
      country: null,
      is_suspended: false,
      is_assigned: false,
      start_date: '2026-01-01',
      expiration_date: '2027-09-01',
    }],
  });

  await act(async () => { await import('@/admin/promo-code-repository'); });

  expect(screen.getByText('LEGACY-USA')).toBeInTheDocument();
  expect(screen.getByText(/USA:/)).toHaveTextContent('USA: 1 available');
  expect(globalThis.fetch).not.toHaveBeenCalled();
});

it('shows embedded requests and fetches once when the recent filter changes', async () => {
  setupPage('admin-season-pass-requests', {
    season: { id: 1, pass_name: 'Winter', pass_year: 2026, spreadsheet_url: null },
    pass_requests: [],
    repo_count: 0,
  });

  await act(async () => { await import('@/admin/season-pass-requests'); });

  expect(screen.getByRole('button', { name: 'Auto Assign from Repository' })).toBeDisabled();
  expect(globalThis.fetch).not.toHaveBeenCalled();
  (globalThis.fetch as jest.Mock).mockResolvedValue(mockJsonResponse({ season: { id: 1 }, pass_requests: [], repo_count: 2 }));
  await act(async () => {
    fireEvent.click(screen.getByLabelText('Show Recently Assigned Only'));
  });
  expect(globalThis.fetch).toHaveBeenCalledTimes(1);
  expect(globalThis.fetch).toHaveBeenCalledWith(
    '/api/admin/seasons/1/pass-requests/list?recent_only=true',
    expect.anything(),
  );
  expect(screen.getByRole('button', { name: 'Auto Assign from Repository' })).toBeEnabled();
});
