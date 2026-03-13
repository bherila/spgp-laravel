import '../bootstrap';

import React, { useState } from 'react';
import { createRoot } from 'react-dom/client';

import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

function ForgotPasswordForm() {
  const mount = document.getElementById('forgot-password');
  const serverErrors = JSON.parse(mount?.getAttribute('data-errors') || '{}');
  const status = mount?.getAttribute('data-status') || '';
  
  const [email, setEmail] = useState('');
  const [errors, setErrors] = useState<Record<string, string[]>>(serverErrors);
  const [isSubmitting, setIsSubmitting] = useState(false);

  const handleSubmit = (e: React.FormEvent) => {
    setIsSubmitting(true);
  };

  return (
    <div className="flex min-h-[60vh] items-center justify-center">
      <Card className="w-full max-w-md">
        <CardHeader>
          <CardTitle>Forgot Password</CardTitle>
          <CardDescription>
            Forgot your password? No problem. Just let us know your email address and we will email you a password reset link that will allow you to choose a new one.
          </CardDescription>
        </CardHeader>
        <CardContent>
          {status && (
            <div className="mb-4 text-sm font-medium text-green-600 dark:text-green-400">
              {status}
            </div>
          )}

          <form method="POST" action="/forgot-password" onSubmit={handleSubmit}>
            <input type="hidden" name="_token" value={document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''} />
            
            <div className="space-y-4">
              <div className="space-y-2">
                <Label htmlFor="email">Email</Label>
                <Input
                  id="email"
                  name="email"
                  type="email"
                  placeholder="you@example.com"
                  value={email}
                  onChange={(e) => setEmail(e.target.value)}
                  required
                  autoFocus
                  autoComplete="email"
                />
                {errors.email && (
                  <p className="text-sm text-destructive">{errors.email[0]}</p>
                )}
              </div>
              
              <Button type="submit" className="w-full" disabled={isSubmitting}>
                {isSubmitting ? 'Sending link...' : 'Email Password Reset Link'}
              </Button>
            </div>
          </form>
          
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
  createRoot(forgotPasswordElement).render(<ForgotPasswordForm />);
}
