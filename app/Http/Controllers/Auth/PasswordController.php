<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class PasswordController extends Controller
{
    // ---------- Forgot password (signed out) ----------

    public function showForgot()
    {
        return view('admin.auth.forgot-password');
    }

    public function sendLink(Request $request)
    {
        $request->validate(['email' => ['required', 'email']]);

        // 'is_active' => true: deactivated users never get a link.
        Password::sendResetLink(['email' => $request->email, 'is_active' => true]);

        // Same answer whether or not the email exists, so nobody can probe which emails are registered.
        return redirect()->back()->with('status', 'If that email belongs to an active account, a reset link has been sent. Check your inbox.');
    }

    public function showReset(Request $request, string $token)
    {
        return view('admin.auth.reset-password', ['token' => $token, 'email' => $request->query('email')]);
    }

    public function reset(Request $request)
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $status = Password::reset(
            [
                'email' => $request->email,
                'password' => $request->password,
                'password_confirmation' => $request->password_confirmation,
                'token' => $request->token,
                'is_active' => true,
            ],
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        return $status === Password::PASSWORD_RESET
            ? redirect()->route('admin.login')->with('status', 'Password changed. Sign in with your new password.')
            : back()->withInput($request->only('email'))->withErrors(['email' => __($status)]);
    }

    // ---------- Change password (signed in) ----------

    public function edit()
    {
        return view('admin.settings.password');
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'string', 'min:8', 'confirmed', 'different:current_password'],
        ]);

        $request->user()->forceFill([
            'password' => Hash::make($data['password']),
            'remember_token' => Str::random(60),
        ])->save();

        return back()->with('success', 'Your password has been changed.');
    }
}
