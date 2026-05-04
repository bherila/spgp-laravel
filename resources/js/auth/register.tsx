import '../bootstrap';

import { type AuthJsonResponse, type AuthSignupField, isAbortError, registerPasskey, SignupForm } from 'bwh-auth';
import { useState } from 'react';
import { createRoot } from 'react-dom/client';

import { getAuthComponents, getCsrfToken } from './shared-components';

function RegisterPage() {
  const mount = document.getElementById('register');
  const serverErrors = JSON.parse(mount?.getAttribute('data-errors') || '{}');
  const csrfToken = getCsrfToken();
  const [status, setStatus] = useState<string | null>(null);
  const [continueUrl, setContinueUrl] = useState<string | null>(null);
  const initialValues = {
    first_name: mount?.getAttribute('data-old-first-name') || '',
    last_name: mount?.getAttribute('data-old-last-name') || '',
    email: mount?.getAttribute('data-old-email') || '',
    invite_code: mount?.getAttribute('data-old-invite-code') || '',
    passwordless: false,
    agreement: mount?.getAttribute('data-old-agreement') === '1',
  };

  const fields: AuthSignupField[] = [
    { name: 'first_name', label: 'First Name', placeholder: 'Your first name', required: true, maxLength: 255, autoComplete: 'given-name' },
    { name: 'last_name', label: 'Last Name', placeholder: 'Your last name', required: true, maxLength: 255, autoComplete: 'family-name' },
    { name: 'email', label: 'Email', type: 'email', placeholder: 'you@example.com', required: true, maxLength: 255, autoComplete: 'email' },
    {
      name: 'passwordless',
      label: 'Use a passkey instead of a password',
      type: 'checkbox',
      helpText: 'You can still create a password later with the password reset email flow.',
    },
    { name: 'password', label: 'Password', type: 'password', placeholder: '••••••••', required: true, minLength: 8, autoComplete: 'new-password', hiddenWhen: (values) => Boolean(values.passwordless) },
    { name: 'password_confirmation', label: 'Confirm Password', type: 'password', placeholder: '••••••••', required: true, minLength: 8, autoComplete: 'new-password', hiddenWhen: (values) => Boolean(values.passwordless) },
    { name: 'invite_code', label: 'Season Invite Code', placeholder: 'Enter your season invite code', required: true },
    {
      name: 'agreement',
      label: 'I agree to keep the details of this program confidential. It may be shared privately with friends and family but must never be shared publicly or posted on social media.',
      type: 'checkbox',
      required: true,
    },
  ];

  async function handleSignupSuccess(result: AuthJsonResponse, values: Record<string, string | boolean>) {
    const redirectUrl = typeof result.redirect === 'string' ? result.redirect : '/dashboard';

    if (!values.passwordless) {
      window.location.assign(redirectUrl);
      return;
    }

    setStatus('Account created. Follow your browser prompt to save a passkey.');
    setContinueUrl(null);

    try {
      await registerPasskey({ endpoints: { csrfToken } });
      window.location.assign(redirectUrl);
    } catch (error) {
      const reason = isAbortError(error) ? 'Passkey setup was canceled.' : 'Passkey setup did not finish.';
      setStatus(`${reason} Your account was created. Continue to your dashboard to add a passkey from account settings, or use password reset by email to create a password.`);
      setContinueUrl(redirectUrl);
    }
  }

  return (
    <div className="flex min-h-[60vh] items-center justify-center">
      <div className="w-full max-w-md">
        <SignupForm
          components={getAuthComponents()}
          endpoints={{ signup: '/register', csrfToken }}
          submitMode="fetch"
          fields={fields}
          initialValues={initialValues}
          errors={serverErrors}
          title="Create an Account"
          description="Enter your details and season invite code to create your account."
          onSuccess={handleSignupSuccess}
          onError={(message) => {
            setStatus(message);
            setContinueUrl(null);
          }}
        />
        {status ? (
          <div className="mt-4 rounded-md border bg-card p-3 text-sm text-muted-foreground">
            <p>{status}</p>
            {continueUrl ? (
              <a href={continueUrl} className="mt-2 inline-block text-primary underline-offset-4 hover:underline">
                Continue to dashboard
              </a>
            ) : null}
          </div>
        ) : null}
        <div className="mt-4 text-center text-sm">
          Already have an account?{' '}
          <a href="/login" className="text-primary underline-offset-4 hover:underline">
            Sign in
          </a>
        </div>
      </div>
    </div>
  );
}

const registerElement = document.getElementById('register');
if (registerElement) {
  createRoot(registerElement).render(<RegisterPage />);
}
