import '../bootstrap';

import { authenticateWithPasskey, isAbortError, isConditionalMediationAvailable, PasskeyLoginButton } from 'bwh-auth';
import React, { useEffect, useMemo, useState } from 'react';
import { createRoot } from 'react-dom/client';

import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

import { getCsrfToken } from './shared-components';

function LoginForm() {
  const mount = document.getElementById('login');
  const serverErrors = JSON.parse(mount?.getAttribute('data-errors') || '{}');
  const oldEmail = mount?.getAttribute('data-old-email') || '';
  
  const [email, setEmail] = useState(oldEmail);
  const [password, setPassword] = useState('');
  const [remember, setRemember] = useState(false);
  const [errors, setErrors] = useState<Record<string, string[]>>(serverErrors);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [passkeyError, setPasskeyError] = useState<string | null>(null);
  const [conditionalPasskeyAvailable, setConditionalPasskeyAvailable] = useState(false);
  const passkeyEndpoints = useMemo(() => ({ csrfToken: getCsrfToken() }), []);

  useEffect(() => {
    const abortController = new AbortController();
    let active = true;

    async function startConditionalPasskeyLogin() {
      const available = await isConditionalMediationAvailable();
      if (!available || !active) return;

      setConditionalPasskeyAvailable(true);

      try {
        const { redirectUrl } = await authenticateWithPasskey({
          endpoints: passkeyEndpoints,
          mediation: 'conditional',
          signal: abortController.signal,
        });

        if (active) {
          window.location.assign(redirectUrl);
        }
      } catch (error) {
        if (!isAbortError(error) && active) {
          setConditionalPasskeyAvailable(false);
        }
      }
    }

    void startConditionalPasskeyLogin();

    return () => {
      active = false;
      abortController.abort();
    };
  }, [passkeyEndpoints]);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setIsSubmitting(true);
    
    // Submit form natively for Laravel session handling
    const form = e.target as HTMLFormElement;
    form.submit();
  };

  return (
    <div className="flex min-h-[60vh] items-center justify-center">
      <Card className="w-full max-w-md">
        <CardHeader>
          <CardTitle>Sign In</CardTitle>
          <CardDescription>Enter your email and password to access your account.</CardDescription>
        </CardHeader>
        <CardContent>
          <form method="POST" action="/login" onSubmit={handleSubmit} noValidate={false}>
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
                  autoComplete={conditionalPasskeyAvailable ? 'username webauthn' : 'email'}
                  aria-invalid={!!errors.email}
                />
                {errors.email && (
                  <p className="text-sm text-destructive">{errors.email[0]}</p>
                )}
              </div>
              
              <div className="space-y-2">
                <div className="flex items-center justify-between">
                  <Label htmlFor="password">Password</Label>
                  <a href="/forgot-password" className="text-sm text-primary underline-offset-4 hover:underline">
                    Forgot your password?
                  </a>
                </div>
                <Input
                  id="password"
                  name="password"
                  type="password"
                  placeholder="••••••••"
                  value={password}
                  onChange={(e) => setPassword(e.target.value)}
                  required
                  minLength={8}
                  autoComplete="current-password"
                />
                {errors.password && (
                  <p className="text-sm text-destructive">{errors.password[0]}</p>
                )}
              </div>
              
              <div className="flex items-center gap-2">
                <input
                  type="checkbox"
                  id="remember"
                  name="remember"
                  checked={remember}
                  onChange={(e) => setRemember(e.target.checked)}
                  className="h-4 w-4 rounded border-gray-300"
                />
                <Label htmlFor="remember" className="text-sm font-normal">Remember me</Label>
              </div>
              
              <Button type="submit" className="w-full" disabled={isSubmitting}>
                {isSubmitting ? 'Signing in...' : 'Sign In'}
              </Button>

              <div className="relative">
                <div className="absolute inset-0 flex items-center">
                  <span className="w-full border-t" />
                </div>
                <div className="relative flex justify-center text-xs uppercase">
                  <span className="bg-card px-2 text-muted-foreground">Or continue with</span>
                </div>
              </div>

              <PasskeyLoginButton
                components={{ Button }}
                endpoints={passkeyEndpoints}
                className="w-full"
                onError={(message) => setPasskeyError(message)}
                onSuccess={(redirectUrl) => window.location.assign(redirectUrl)}
              />

              {passkeyError && (
                <p className="text-sm text-destructive">{passkeyError}</p>
              )}
            </div>
          </form>
          
          <div className="mt-4 text-center text-sm">
            Don't have an account?{' '}
            <a href="/register" className="text-primary underline-offset-4 hover:underline">
              Sign up
            </a>
          </div>
        </CardContent>
      </Card>
    </div>
  );
}

const loginElement = document.getElementById('login');
if (loginElement) {
  createRoot(loginElement).render(<LoginForm />);
}
