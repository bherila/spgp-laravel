import '../bootstrap';

import { ResetPasswordForm } from 'bwh-auth';
import { useState } from 'react';
import { createRoot } from 'react-dom/client';

import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';

import { getAuthComponents, getCsrfToken } from './shared-components';

function ResetPasswordPage() {
  const mount = document.getElementById('reset-password');
  const token = mount?.getAttribute('data-token') || '';
  const email = mount?.getAttribute('data-email') || '';
  const [error, setError] = useState('');

  return (
    <div className="flex min-h-[60vh] items-center justify-center">
      <Card className="w-full max-w-md">
        <CardHeader>
          <CardTitle>Reset Password</CardTitle>
          <CardDescription>Enter your new password below.</CardDescription>
        </CardHeader>
        <CardContent>
          {error ? <div className="mb-4 text-sm font-medium text-destructive">{error}</div> : null}
          <ResetPasswordForm
            components={getAuthComponents()}
            endpoints={{ csrfToken: getCsrfToken() }}
            token={token}
            email={email}
            onSuccess={(result) => {
              window.location.href = result.redirect || '/dashboard';
            }}
            onError={setError}
          />
        </CardContent>
      </Card>
    </div>
  );
}

const resetPasswordElement = document.getElementById('reset-password');
if (resetPasswordElement) {
  createRoot(resetPasswordElement).render(<ResetPasswordPage />);
}
