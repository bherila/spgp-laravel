import '../bootstrap';
import { createRoot } from 'react-dom/client';
import React, { useState } from 'react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

function RegisterForm() {
  const mount = document.getElementById('register');
  const serverErrors = JSON.parse(mount?.getAttribute('data-errors') || '{}');
  const oldName = mount?.getAttribute('data-old-name') || '';
  const oldEmail = mount?.getAttribute('data-old-email') || '';
  const oldInviteCode = mount?.getAttribute('data-old-invite-code') || '';
  const oldAgreement = mount?.getAttribute('data-old-agreement') === '1';
  
  const [name, setName] = useState(oldName);
  const [email, setEmail] = useState(oldEmail);
  const [password, setPassword] = useState('');
  const [passwordConfirmation, setPasswordConfirmation] = useState('');
  const [inviteCode, setInviteCode] = useState(oldInviteCode);
  const [agreement, setAgreement] = useState(oldAgreement);
  const [errors, setErrors] = useState<Record<string, string[]>>(serverErrors);
  const [isSubmitting, setIsSubmitting] = useState(false);

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
          <CardTitle>Create an Account</CardTitle>
          <CardDescription>Enter your details and season invite code to create your account.</CardDescription>
        </CardHeader>
        <CardContent>
          <form method="POST" action="/register" onSubmit={handleSubmit} noValidate={false}>
            <input type="hidden" name="_token" value={document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''} />
            
            <div className="space-y-4">
              <div className="space-y-2">
                <Label htmlFor="name">Name</Label>
                <Input
                  id="name"
                  name="name"
                  type="text"
                  placeholder="Your name"
                  value={name}
                  onChange={(e) => setName(e.target.value)}
                  required
                  maxLength={255}
                  autoComplete="name"
                  aria-invalid={!!errors.name}
                />
                {errors.name && (
                  <p className="text-sm text-destructive">{errors.name[0]}</p>
                )}
              </div>
              
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
                  maxLength={255}
                  autoComplete="email"
                  aria-invalid={!!errors.email}
                />
                {errors.email && (
                  <p className="text-sm text-destructive">{errors.email[0]}</p>
                )}
              </div>
              
              <div className="space-y-2">
                <Label htmlFor="password">Password</Label>
                <Input
                  id="password"
                  name="password"
                  type="password"
                  placeholder="••••••••"
                  value={password}
                  onChange={(e) => setPassword(e.target.value)}
                  required
                  minLength={8}
                  autoComplete="new-password"
                  aria-invalid={!!errors.password}
                />
                {errors.password && (
                  <p className="text-sm text-destructive">{errors.password[0]}</p>
                )}
              </div>
              
              <div className="space-y-2">
                <Label htmlFor="password_confirmation">Confirm Password</Label>
                <Input
                  id="password_confirmation"
                  name="password_confirmation"
                  type="password"
                  placeholder="••••••••"
                  value={passwordConfirmation}
                  onChange={(e) => setPasswordConfirmation(e.target.value)}
                  required
                  minLength={8}
                  autoComplete="new-password"
                />
              </div>
              
              <div className="space-y-2">
                <Label htmlFor="invite_code">Season Invite Code</Label>
                <Input
                  id="invite_code"
                  name="invite_code"
                  type="text"
                  placeholder="Enter your season invite code"
                  value={inviteCode}
                  onChange={(e) => setInviteCode(e.target.value)}
                  required
                  aria-invalid={!!errors.invite_code}
                />
                {errors.invite_code && (
                  <p className="text-sm text-destructive">{errors.invite_code[0]}</p>
                )}
              </div>

              <div className="flex items-start space-x-2">
                <input
                  id="agreement"
                  name="agreement"
                  type="checkbox"
                  checked={agreement}
                  onChange={(e) => setAgreement(e.target.checked)}
                  required
                  className="mt-1 h-4 w-4"
                />
                <Label htmlFor="agreement" className="-mt-1">
                  I agree to keep the details of this program confidential. It may be
                  shared privately with friends and family but must never be shared
                  publicly or posted on social media.
                </Label>
              </div>
              {errors.agreement && (
                <p className="text-sm text-destructive">{errors.agreement[0]}</p>
              )}
              
              <Button type="submit" className="w-full" disabled={isSubmitting}>
                {isSubmitting ? 'Creating account...' : 'Create Account'}
              </Button>
            </div>
          </form>
          
          <div className="mt-4 text-center text-sm">
            Already have an account?{' '}
            <a href="/login" className="text-primary underline-offset-4 hover:underline">
              Sign in
            </a>
          </div>
        </CardContent>
      </Card>
    </div>
  );
}

const registerElement = document.getElementById('register');
if (registerElement) {
  createRoot(registerElement).render(<RegisterForm />);
}
