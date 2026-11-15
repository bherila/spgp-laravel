import { fireEvent, render, screen, waitFor, within } from '@testing-library/react';

import Navbar from './navbar';

it('recovers passkey enrollment through the real shared confirmation UI and app endpoints', async () => {
  const originalCredential = Object.getOwnPropertyDescriptor(window, 'PublicKeyCredential');
  const originalMatchMedia = Object.getOwnPropertyDescriptor(window, 'matchMedia');
  Object.defineProperty(window, 'PublicKeyCredential', { configurable: true, value: jest.fn() });
  Object.defineProperty(window, 'matchMedia', {
    configurable: true,
    value: jest.fn(() => ({ matches: false, addEventListener: jest.fn(), removeEventListener: jest.fn() })),
  });
  let registrationAttempts = 0;
  const originalFetch = globalThis.fetch;
  globalThis.fetch = jest.fn(async (input: RequestInfo | URL) => {
    const url = typeof input === 'string' ? input : input instanceof URL ? input.href : input.url;
    if (url === '/api/passkeys/register/options') {
      registrationAttempts += 1;
      return { ok: false, status: 423, json: async () => ({ error: 'password_confirmation_required' }) } as Response;
    }
    return { ok: true, status: 200, json: async () => url === '/api/passkeys' ? [] : { success: true } } as Response;
  });

  try {
    render(<Navbar authenticated isAdmin={false} />);
    fireEvent.click(screen.getByRole('button', { name: 'Account' }));
    fireEvent.click(screen.getByRole('button', { name: 'Account Settings' }));
    await screen.findByText('No passkeys registered yet.');
    fireEvent.click(screen.getByRole('button', { name: 'Add Passkey' }));
    const confirmation = await screen.findByRole('group', { name: 'Confirm your identity' });
    expect(within(confirmation).getByRole('button', { name: 'Use existing passkey' })).toBeInTheDocument();
    fireEvent.change(within(confirmation).getByLabelText('Current password'), { target: { value: 'current-password' } });
    fireEvent.click(within(confirmation).getByRole('button', { name: 'Confirm password and continue' }));
    await waitFor(() => expect(registrationAttempts).toBe(2));
    expect(globalThis.fetch).toHaveBeenCalledWith('/api/auth/confirm-password', expect.objectContaining({
      method: 'POST', body: JSON.stringify({ password: 'current-password' }),
    }));
    expect(await within(confirmation).findByRole('alert')).toHaveTextContent('Confirmation is still required.');
  } finally {
    globalThis.fetch = originalFetch;
    if (originalCredential) Object.defineProperty(window, 'PublicKeyCredential', originalCredential);
    else Reflect.deleteProperty(window, 'PublicKeyCredential');
    if (originalMatchMedia) Object.defineProperty(window, 'matchMedia', originalMatchMedia);
    else Reflect.deleteProperty(window, 'matchMedia');
  }
});
