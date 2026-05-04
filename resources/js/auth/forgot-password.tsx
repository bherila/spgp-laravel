import '../bootstrap';

import { PasswordResetRequestForm } from 'bwh-auth';
import { useState } from 'react';
import { createRoot } from 'react-dom/client';

import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';

import { getAuthComponents, getCsrfToken } from './shared-components';

function ForgotPasswordPage() {
  const mount = document.getElementById('forgot-password');
  const initialStatus = mount?.getAttribute('data-status') || '';
  const [status, setStatus] = useState(initialStatus);
  const [error, setError] = useState('');

  return (
    <div className="flex min-h-[60vh] items-center justify-center">
      <Card className="w-full max-w-md">
        <CardHeader>
          <CardTitle>Forgot Password</CardTitle>
          <CardDescription>
            Forgot your password? No problem. Enter your email address and we will email you a password reset link.
          </CardDescription>
        </CardHeader>
        <CardContent>
          {status ? <div className="mb-4 text-sm font-medium text-green-600 dark:text-green-400">{status}</div> : null}
          {error ? <div className="mb-4 text-sm font-medium text-destructive">{error}</div> : null}
          <PasswordResetRequestForm
            components={getAuthComponents()}
            endpoints={{ csrfToken: getCsrfToken() }}
            onSuccess={(result) => {
              setError('');
              setStatus(result.message || 'If an account exists with this email, a password reset link has been sent.');
            }}
            onError={(message) => {
              setStatus('');
              setError(message);
            }}
          />
          <div className="mt-4 text-center text-sm">
            <a href="/login" className="text-primary underline-offset-4 hover:underline">
              Back to login
            </a>
          </div>
        </CardContent>
      </Card>
    </div>
  );
}

const forgotPasswordElement = document.getElementById('forgot-password');
if (forgotPasswordElement) {
  createRoot(forgotPasswordElement).render(<ForgotPasswordPage />);
}
