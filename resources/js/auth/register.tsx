import '../bootstrap';

import { type AuthSignupField,SignupForm } from 'bwh-auth';
import { createRoot } from 'react-dom/client';

import { getAuthComponents, getCsrfToken } from './shared-components';

function RegisterPage() {
  const mount = document.getElementById('register');
  const serverErrors = JSON.parse(mount?.getAttribute('data-errors') || '{}');
  const initialValues = {
    first_name: mount?.getAttribute('data-old-first-name') || '',
    last_name: mount?.getAttribute('data-old-last-name') || '',
    email: mount?.getAttribute('data-old-email') || '',
    invite_code: mount?.getAttribute('data-old-invite-code') || '',
    agreement: mount?.getAttribute('data-old-agreement') === '1',
  };

  const fields: AuthSignupField[] = [
    { name: 'first_name', label: 'First Name', placeholder: 'Your first name', required: true, maxLength: 255, autoComplete: 'given-name' },
    { name: 'last_name', label: 'Last Name', placeholder: 'Your last name', required: true, maxLength: 255, autoComplete: 'family-name' },
    { name: 'email', label: 'Email', type: 'email', placeholder: 'you@example.com', required: true, maxLength: 255, autoComplete: 'email' },
    { name: 'password', label: 'Password', type: 'password', placeholder: '••••••••', required: true, minLength: 8, autoComplete: 'new-password' },
    { name: 'password_confirmation', label: 'Confirm Password', type: 'password', placeholder: '••••••••', required: true, minLength: 8, autoComplete: 'new-password' },
    { name: 'invite_code', label: 'Season Invite Code', placeholder: 'Enter your season invite code', required: true },
    {
      name: 'agreement',
      label: 'I agree to keep the details of this program confidential. It may be shared privately with friends and family but must never be shared publicly or posted on social media.',
      type: 'checkbox',
      required: true,
    },
  ];

  return (
    <div className="flex min-h-[60vh] items-center justify-center">
      <div className="w-full max-w-md">
        <SignupForm
          components={getAuthComponents()}
          endpoints={{ signup: '/register', csrfToken: getCsrfToken() }}
          submitMode="native"
          fields={fields}
          initialValues={initialValues}
          errors={serverErrors}
          title="Create an Account"
          description="Enter your details and season invite code to create your account."
        />
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
