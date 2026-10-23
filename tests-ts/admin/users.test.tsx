import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import React from 'react';

import { AdminUsers } from '@/admin/users';

import { mockJsonResponse } from '../helpers/http';

jest.mock('../../resources/js/bootstrap', () => ({}));

const user = {
  id: 1,
  first_name: 'Alex',
  last_name: 'Example',
  name: 'Alex Example',
  email: 'alex@example.test',
  is_admin: false,
  pass_request_count: 0,
  last_login_at: null,
  created_at: '2026-10-23T12:00:00+00:00',
  updated_at: '2026-10-23T12:00:00+00:00',
};

beforeEach(() => {
  globalThis.fetch = jest.fn();
  document.head.innerHTML = '<meta name="csrf-token" content="test-csrf">';
});

afterEach(() => {
  document.head.innerHTML = '';
});

it('updates a user with the shared JSON/CSRF client and refreshes after success', async () => {
  (globalThis.fetch as jest.Mock)
    .mockResolvedValueOnce(mockJsonResponse(user))
    .mockResolvedValueOnce(mockJsonResponse([{ ...user, first_name: 'Updated' }]));
  render(<AdminUsers initialUsers={[user]} />);
  expect(globalThis.fetch).not.toHaveBeenCalled();
  fireEvent.click(screen.getByRole('button', { name: 'Edit alex@example.test' }));
  fireEvent.change(screen.getByLabelText('First Name'), { target: { value: 'Updated' } });
  fireEvent.click(screen.getByRole('button', { name: 'Save Changes' }));

  await waitFor(() => expect(screen.getByText('Updated Example')).toBeInTheDocument());
  expect(globalThis.fetch).toHaveBeenCalledTimes(2);
  const [url, options] = (globalThis.fetch as jest.Mock).mock.calls[0];
  expect(url).toBe('/api/admin/users/1');
  expect(options.method).toBe('PUT');
  expect(options.credentials).toBe('same-origin');
  expect(options.headers.get('X-CSRF-TOKEN')).toBe('test-csrf');
  expect(options.headers.get('Content-Type')).toBe('application/json');
  expect(JSON.parse(options.body)).toEqual({
    first_name: 'Updated', last_name: 'Example', email: user.email, is_admin: false,
  });
});

it('shows server validation errors without retrying or refreshing the list', async () => {
  (globalThis.fetch as jest.Mock).mockResolvedValue(mockJsonResponse({
    message: 'That email address is already registered.',
    errors: { email: ['That email address is already registered.'] },
  }, 422));
  render(<AdminUsers initialUsers={[user]} />);
  fireEvent.click(screen.getByRole('button', { name: 'Edit alex@example.test' }));
  fireEvent.click(screen.getByRole('button', { name: 'Save Changes' }));

  expect(await screen.findByText('That email address is already registered.')).toBeInTheDocument();
  expect(globalThis.fetch).toHaveBeenCalledTimes(1);
  expect(screen.getByRole('dialog')).toBeInTheDocument();
});
