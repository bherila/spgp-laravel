<?php

namespace App\Http\Controllers;

use App\Models\InviteCode;
use App\Models\User;
use App\Models\UserLogin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
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
            
            // Log successful login
            UserLogin::logSuccess(
                Auth::user(),
                $request->ip(),
                $request->userAgent()
            );
            
            return redirect()->intended('/dashboard');
        }

        // Log failed login
        UserLogin::logFailure(
            $credentials['email'],
            'Invalid credentials',
            $request->ip(),
            $request->userAgent()
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
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'invite_code' => ['required', 'string'],
        ]);

        // Validate invite code
        $inviteCode = InviteCode::where('invite_code', $validated['invite_code'])->first();

        if (!$inviteCode) {
            return back()->withErrors([
                'invite_code' => 'Invalid invite code.',
            ])->withInput();
        }

        if (!$inviteCode->canBeUsed()) {
            return back()->withErrors([
                'invite_code' => 'This invite code has reached its maximum number of uses.',
            ])->withInput();
        }

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'invite_code_id' => $inviteCode->id,
        ]);

        Auth::login($user);

        return redirect('/dashboard');
    }

    /**
     * Handle logout request.
     */
    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }

    /**
     * Change the user's password.
     */
    public function changePassword(Request $request)
    {
        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user = $request->user();

        if (!Hash::check($validated['current_password'], $user->password)) {
            return response()->json([
                'message' => 'The current password is incorrect.',
                'errors' => [
                    'current_password' => ['The current password is incorrect.'],
                ],
            ], 422);
        }

        $user->update([
            'password' => Hash::make($validated['password']),
        ]);

        return response()->json([
            'message' => 'Password changed successfully.',
        ]);
    }

    /**
     * Impersonate a user (admin only).
     */
    public function impersonate(Request $request, $id)
    {
        $admin = $request->user();
        
        if (!$admin->isAdmin()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $user = User::findOrFail($id);

        // Log the impersonation as a login
        UserLogin::create([
            'user_id' => $user->id,
            'email' => $user->email,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'successful' => true,
            'failure_reason' => "Impersonated by admin: {$admin->email}",
        ]);

        Auth::login($user);
        $request->session()->regenerate();

        return response()->json([
            'message' => 'Now logged in as ' . $user->name,
            'redirect' => '/dashboard',
        ]);
    }
}
