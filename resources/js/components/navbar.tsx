import { ChangePasswordForm, PasskeySection } from 'bwh-auth';
import { ChevronDown, Key, Laptop, LogOut, Menu, Moon, Settings, Sun, X } from 'lucide-react';
import * as React from 'react';
import { useEffect, useRef, useState } from 'react';

import { getAuthComponents, getCsrfToken } from '@/auth/shared-components';
import { Alert, AlertDescription } from '@/components/ui/alert';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';

type NavbarProps = {
  authenticated: boolean;
  isAdmin: boolean;
};

type ThemeMode = 'system' | 'dark' | 'light';

function applyTheme(mode: ThemeMode) {
  const root = document.documentElement;
  const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
  const isDark = mode === 'dark' || (mode === 'system' && prefersDark);
  root.classList.toggle('dark', isDark);
}

export default function Navbar({ authenticated, isAdmin }: NavbarProps) {
  const [adminMenuOpen, setAdminMenuOpen] = useState(false);
  const [userMenuOpen, setUserMenuOpen] = useState(false);
  const [mobileMenuOpen, setMobileMenuOpen] = useState(false);
  const [accountSettingsOpen, setAccountSettingsOpen] = useState(false);
  const adminMenuRef = useRef<HTMLLIElement | null>(null);
  const userMenuRef = useRef<HTMLDivElement | null>(null);
  const [theme, setTheme] = useState<ThemeMode>(() => (localStorage.getItem('theme') as ThemeMode) || 'system');

  // Account settings state
  const [passwordError, setPasswordError] = useState<string | null>(null);
  const [passwordSuccess, setPasswordSuccess] = useState<string | null>(null);
  const [passkeyError, setPasskeyError] = useState<string | null>(null);
  const [passkeySuccess, setPasskeySuccess] = useState<string | null>(null);

  useEffect(() => {
    applyTheme(theme);
    localStorage.setItem('theme', theme);
  }, [theme]);

  useEffect(() => {
    const handler = (e: MouseEvent) => {
      if (adminMenuRef.current && !adminMenuRef.current.contains(e.target as Node)) {
        setAdminMenuOpen(false);
      }
      if (userMenuRef.current && !userMenuRef.current.contains(e.target as Node)) {
        setUserMenuOpen(false);
      }
    };
    document.addEventListener('mousedown', handler);
    return () => document.removeEventListener('mousedown', handler);
  }, []);

  useEffect(() => {
    const mq = window.matchMedia('(prefers-color-scheme: dark)');
    const onChange = () => {
      const saved = (localStorage.getItem('theme') as ThemeMode) || 'system';
      if (saved === 'system') applyTheme('system');
    };
    mq.addEventListener('change', onChange);
    return () => mq.removeEventListener('change', onChange);
  }, []);

  return (
    <nav className='relative mx-auto max-w-7xl px-4 py-3'>
      <div className='flex items-center justify-between gap-4'>
        {/* Left: Branding + Main nav */}
        <div className='flex items-center gap-6'>
          <button
            type='button'
            className='md:hidden p-1 -ml-1 hover:bg-muted rounded-md'
            onClick={() => setMobileMenuOpen(!mobileMenuOpen)}
            aria-label='Toggle mobile menu'
          >
            {mobileMenuOpen ? <X className='w-6 h-6' /> : <Menu className='w-6 h-6' />}
          </button>
          
          <a href='/' className='select-none'>
            <h1 className='text-lg font-semibold tracking-tight'>Pass Group</h1>
          </a>
          
          <ul className='hidden md:flex items-center gap-4 text-sm'>
            {authenticated && (
              <li><a className='hover:underline underline-offset-4' href='/dashboard'>Dashboard</a></li>
            )}
            {isAdmin && (
              <li ref={adminMenuRef} className='relative'>
                <button
                  type='button'
                  onClick={() => setAdminMenuOpen(!adminMenuOpen)}
                  className='flex items-center gap-1 hover:underline underline-offset-4'
                >
                  <Settings className='w-4 h-4' />
                  Admin
                  <ChevronDown className={`w-3 h-3 transition-transform ${adminMenuOpen ? 'rotate-180' : ''}`} />
                </button>
                {adminMenuOpen && (
                  <div className='absolute top-full left-0 mt-1 w-40 bg-background border rounded-md shadow-lg z-50'>
                    <a
                      href='/admin/users'
                      className='block px-4 py-2 text-sm hover:bg-muted'
                    >
                      Users
                    </a>
                    <a
                      href='/admin/invites'
                      className='block px-4 py-2 text-sm hover:bg-muted'
                    >
                      Invites
                    </a>
                    <a
                      href='/admin/seasons'
                      className='block px-4 py-2 text-sm hover:bg-muted'
                    >
                      Seasons
                    </a>
                    <a
                      href='/admin/email-log'
                      className='block px-4 py-2 text-sm hover:bg-muted'
                    >
                      Email Log
                    </a>
                  </div>
                )}
              </li>
            )}
          </ul>
        </div>

        {/* Right: Auth + Theme toggle */}
        <div className='flex items-center gap-3'>
          {authenticated ? (
            <div ref={userMenuRef} className='relative'>
              <button
                type='button'
                onClick={() => setUserMenuOpen(!userMenuOpen)}
                className='flex items-center gap-1 text-sm hover:underline underline-offset-4'
              >
                Account
                <ChevronDown className={`w-3 h-3 transition-transform ${userMenuOpen ? 'rotate-180' : ''}`} />
              </button>
              {userMenuOpen && (
                <div className='absolute top-full right-0 mt-1 w-auto min-w-[10rem] bg-background border rounded-md shadow-lg z-50'>
                  <button
                    type='button'
                    onClick={() => {
                      setUserMenuOpen(false);
                      setAccountSettingsOpen(true);
                    }}
                    className='block w-full text-left px-4 py-2 text-sm hover:bg-muted whitespace-nowrap'
                  >
                    <Key className='w-4 h-4 inline mr-2' />
                    Account Settings
                  </button>
                  <form method='POST' action='/logout' className='block'>
                    <input type='hidden' name='_token' value={document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''} />
                    <button type='submit' className='block w-full text-left px-4 py-2 text-sm hover:bg-muted whitespace-nowrap'>
                      <LogOut className='w-4 h-4 inline mr-2' />
                      Sign Out
                    </button>
                  </form>
                </div>
              )}
            </div>
          ) : (
            <a href='/login' className='text-sm hover:underline underline-offset-4'>
              Sign In
            </a>
          )}
          
          {/* Tri-state theme toggle */}
          <div className='inline-flex items-center overflow-hidden rounded-md border border-gray-200 dark:border-[#3E3E3A]'>
            <button
              type='button'
              onClick={() => setTheme('system')}
              className={`px-2 py-1.5 hover:bg-gray-50 dark:hover:bg-[#1f1f1e] ${
                theme === 'system'
                  ? 'bg-gray-900 text-white dark:bg-[#262625] dark:text-gray-50'
                  : 'text-gray-500 dark:text-gray-400'
              }`}
              title='System'
              aria-pressed={theme === 'system'}
            >
              <Laptop className='w-4 h-4' />
            </button>
            <button
              type='button'
              onClick={() => setTheme('dark')}
              className={`px-2 py-1.5 hover:bg-gray-50 dark:hover:bg-[#1f1f1e] ${
                theme === 'dark'
                  ? 'bg-gray-900 text-white dark:bg-[#262625] dark:text-gray-50'
                  : 'text-gray-500 dark:text-gray-400'
              }`}
              title='Dark'
              aria-pressed={theme === 'dark'}
            >
              <Moon className='w-4 h-4' />
            </button>
            <button
              type='button'
              onClick={() => setTheme('light')}
              className={`px-2 py-1.5 hover:bg-gray-50 dark:hover:bg-[#1f1f1e] ${
                theme === 'light'
                  ? 'bg-gray-900 text-white dark:bg-[#262625] dark:text-gray-50'
                  : 'text-gray-500 dark:text-gray-400'
              }`}
              title='Light'
              aria-pressed={theme === 'light'}
            >
              <Sun className='w-4 h-4' />
            </button>
          </div>
        </div>
      </div>

      {/* Mobile Menu */}
      {mobileMenuOpen && (
        <div className='md:hidden absolute top-full left-0 right-0 bg-background border-b shadow-lg z-50 px-4 py-4 animate-in fade-in slide-in-from-top-4 duration-200'>
          <ul className='space-y-4'>
            {authenticated && (
              <li>
                <a 
                  className='block font-medium text-sm py-1' 
                  href='/dashboard'
                  onClick={() => setMobileMenuOpen(false)}
                >
                  Dashboard
                </a>
              </li>
            )}
            {isAdmin && (
              <li className='space-y-3'>
                <div className='flex items-center gap-2 text-sm font-semibold text-muted-foreground uppercase tracking-wider'>
                  <Settings className='w-4 h-4' />
                  Admin
                </div>
                <ul className='pl-6 space-y-3 border-l ml-2'>
                  <li>
                    <a 
                      href='/admin/users' 
                      className='block text-sm hover:underline'
                      onClick={() => setMobileMenuOpen(false)}
                    >
                      Users
                    </a>
                  </li>
                  <li>
                    <a 
                      href='/admin/invites' 
                      className='block text-sm hover:underline'
                      onClick={() => setMobileMenuOpen(false)}
                    >
                      Invites
                    </a>
                  </li>
                  <li>
                    <a 
                      href='/admin/seasons' 
                      className='block text-sm hover:underline'
                      onClick={() => setMobileMenuOpen(false)}
                    >
                      Seasons
                    </a>
                  </li>
                  <li>
                    <a 
                      href='/admin/email-log' 
                      className='block text-sm hover:underline'
                      onClick={() => setMobileMenuOpen(false)}
                    >
                      Email Log
                    </a>
                  </li>
                </ul>
              </li>
            )}
          </ul>
        </div>
      )}

      {/* Account Settings Dialog */}
      <Dialog open={accountSettingsOpen} onOpenChange={setAccountSettingsOpen}>
        <DialogContent className="max-w-2xl">
          <DialogHeader>
            <DialogTitle>Account Settings</DialogTitle>
            <DialogDescription>
              Manage your password and passkeys.
            </DialogDescription>
          </DialogHeader>
          <div className="space-y-6 py-4">
            <div className="space-y-4">
              <div>
                <h3 className="font-medium">Password</h3>
                <p className="text-sm text-muted-foreground">Enter your current password and choose a new password.</p>
              </div>
              {passwordError && (
                <Alert variant="destructive">
                  <AlertDescription>{passwordError}</AlertDescription>
                </Alert>
              )}
              {passwordSuccess && (
                <Alert>
                  <AlertDescription className="text-green-600">{passwordSuccess}</AlertDescription>
                </Alert>
              )}
              <ChangePasswordForm
                components={getAuthComponents()}
                endpoints={{ csrfToken: getCsrfToken() }}
                onSuccess={(result) => {
                  setPasswordError(null);
                  setPasswordSuccess(result.message || 'Password changed successfully.');
                  setTimeout(() => {
                    setAccountSettingsOpen(false);
                    setPasswordSuccess(null);
                  }, 2000);
                }}
                onError={(message) => {
                  setPasswordSuccess(null);
                  setPasswordError(message);
                }}
              />
            </div>

            <div className="space-y-4">
              {passkeyError && (
                <Alert variant="destructive">
                  <AlertDescription>{passkeyError}</AlertDescription>
                </Alert>
              )}
              {passkeySuccess && (
                <Alert>
                  <AlertDescription className="text-green-600">{passkeySuccess}</AlertDescription>
                </Alert>
              )}
              <PasskeySection
                components={getAuthComponents()}
                endpoints={{ csrfToken: getCsrfToken() }}
                onSuccess={(message) => {
                  setPasskeyError(null);
                  setPasskeySuccess(message);
                }}
                onError={(_field, message) => {
                  setPasskeySuccess(null);
                  setPasskeyError(message);
                }}
              />
            </div>
          </div>
        </DialogContent>
      </Dialog>
    </nav>
  );
}
