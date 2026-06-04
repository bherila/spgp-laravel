<?php

namespace App\Http\Controllers;

use App\Models\InviteCode;
use App\Models\User;
use BWH\Auth\Concerns\LogsAuthEvents;
use BWH\Auth\Models\AuthAuditLog;
use BWH\Auth\Support\ClientIp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;

class AuthController extends Controller
{
    use LogsAuthEvents;

    /**
     * Show the login form.
     */
    public function showLogin()
    {
        if (Auth::check()) {
            return redirect('/dashboard');
        }

        return view('auth.login');
    }

    /**
     * Show the forgot password form.
     */
    public function showForgotPassword()
    {
        return view('auth.forgot-password');
    }

    /**
     * Handle forgot password request.
     */
    public function sendResetLinkEmail(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        $status = Password::sendResetLink(
            $request->only('email')
        );

        return $status === Password::RESET_LINK_SENT
            ? back()->with(['status' => __($status)])
            : back()->withErrors(['email' => __($status)]);
    }

    /**
     * Show the reset password form.
     */
    public function showResetPassword($token, Request $request)
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => $request->email,
        ]);
    }

    /**
     * Handle reset password request.
     */
    public function resetPassword(Request $request)
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => ['required', 'confirmed', PasswordRule::defaults()],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                Auth::login($user);
            }
        );

        return $status === Password::PASSWORD_RESET
            ? redirect('/dashboard')->with('status', __($status))
            : back()->withErrors(['email' => [__($status)]]);
    }

    /**
     * Handle login request.
     */
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();

            $user = $request->user();

            if ($user !== null) {
                $this->auditLoginSucceeded($request, $user);
            }

            return redirect()->intended('/dashboard');
        }

        $existingUser = User::where('email', $credentials['email'])->first();

        $this->auditLoginFailed(
            $request,
            $existingUser,
            $credentials['email'],
            $existingUser ? 'Invalid password' : 'Unknown email',
        );

        return back()->withErrors([
            'email' => 'The provided credentials do not match our records.',
        ])->onlyInput('email');
    }

    /**
     * Show the registration form.
     */
    public function showRegister()
    {
        if (Auth::check()) {
            return redirect('/dashboard');
        }

        return view('auth.register');
    }

    /**
     * Handle registration request.
     */
    public function register(Request $request)
    {
        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'confirmed', PasswordRule::defaults()],
            'invite_code' => ['required', 'string'],
            // user must agree to confidentially keep program details
            'agreement' => ['accepted'],
        ]);

        // Validate invite code
        $inviteCode = InviteCode::where('invite_code', $validated['invite_code'])->first();

        if (! $inviteCode) {
            return back()->withErrors([
                'invite_code' => 'Invalid invite code.',
            ])->withInput();
        }

        if (! $inviteCode->canBeUsed()) {
            Log::info("Invite code {$inviteCode->invite_code} is exhausted. Usage: {$inviteCode->users()->count()}, Max: {$inviteCode->max_number_of_uses}");

            return back()->withErrors([
                'invite_code' => 'This invite code has reached its maximum number of uses.',
            ])->withInput();
        }

        $user = User::create([
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        // Attach invite code to user
        $user->inviteCodes()->attach($inviteCode->id);

        Auth::login($user);

        return redirect('/dashboard');
    }

    /**
     * Handle logout request.
     */
    public function logout(Request $request)
    {
        $this->auditLoggedOut($request, $request->user());

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }

    /**
     * Impersonate a user (admin only).
     */
    public function impersonate(Request $request, $id)
    {
        $admin = $request->user();

        if (! $admin->isAdmin()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $user = User::findOrFail($id);

        AuthAuditLog::create([
            'user_id' => $user->id,
            'acting_user_id' => $admin->id,
            'email' => $user->email,
            'event' => AuthAuditLog::EVENT_LOGIN_SUCCEEDED,
            'auth_method' => 'impersonation',
            'succeeded' => true,
            'ip_address' => ClientIp::resolve($request),
            'user_agent' => $request->userAgent(),
            'session_id' => $request->hasSession() ? $request->session()->getId() : null,
            'metadata' => ['impersonated_by' => $admin->email],
        ]);

        Auth::login($user);
        $request->session()->regenerate();

        return response()->json([
            'message' => 'Now logged in as '.$user->first_name,
            'redirect' => '/dashboard',
        ]);
    }
}
