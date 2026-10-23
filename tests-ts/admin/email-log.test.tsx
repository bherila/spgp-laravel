import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import React from 'react';

import { EmailLogAdmin } from '@/admin/email-log';

import { mockJsonResponse } from '../helpers/http';

jest.mock('../../resources/js/bootstrap', () => ({}));

const initialData = {
  emailLogs: [{
    id: 1,
    event: 'test',
    email_to: 'initial@example.test',
    email_from: 'sender@example.test',
    subject: 'Initial email',
    body: 'Initial content',
    result: 'sent',
    error_message: null,
    created_at: '2026-10-23T12:00:00+00:00',
  }],
  total: 100,
  page: 2,
  limit: 50,
};

beforeEach(() => {
  globalThis.fetch = jest.fn().mockResolvedValue(mockJsonResponse({ ...initialData, total: 75, page: 1 }));
});

it('renders the embedded email page without fetching on mount or while typing', () => {
  render(<EmailLogAdmin initialData={initialData} />);

  expect(screen.getByText('Initial email')).toBeInTheDocument();
  fireEvent.change(screen.getByPlaceholderText('Search by recipient...'), { target: { value: 'changed@example.test' } });
  expect(globalThis.fetch).not.toHaveBeenCalled();
});

it('searches page one once and paginates with the submitted filters', async () => {
  render(<EmailLogAdmin initialData={initialData} />);
  fireEvent.change(screen.getByPlaceholderText('Search by recipient...'), { target: { value: 'filtered@example.test' } });
  fireEvent.click(screen.getByRole('button', { name: 'Search' }));

  await waitFor(() => expect(screen.getByText('Page 1 of 2 (75 total)')).toBeInTheDocument());
  expect(globalThis.fetch).toHaveBeenCalledTimes(1);
  expect(globalThis.fetch).toHaveBeenNthCalledWith(1,
    '/api/admin/email-log/list?page=1&limit=50&to=filtered%40example.test',
    expect.anything(),
  );

  fireEvent.change(screen.getByPlaceholderText('Search by recipient...'), { target: { value: 'unsent-draft@example.test' } });
  fireEvent.click(screen.getByRole('button', { name: 'Next' }));
  await waitFor(() => expect(globalThis.fetch).toHaveBeenCalledTimes(2));
  expect(globalThis.fetch).toHaveBeenNthCalledWith(2,
    '/api/admin/email-log/list?page=2&limit=50&to=filtered%40example.test',
    expect.anything(),
  );
});
