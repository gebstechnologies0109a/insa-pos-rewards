<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    public function showLoginForm()
    {
        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();

            $user = Auth::user();

            if (is_epayplus_product($request)) {
                if ($user->isSuperAdmin() || in_array($user->role, ['owner', 'admin'], true)) {
                    return redirect()->intended(route('epayplus.dashboard'));
                }

                abort(403, 'This account is not authorized for ePay Plus Admin.');
            }

            if (is_insa_product($request) && is_insa_android_app($request)) {
                return $this->redirectInsaAndroidAfterLogin($user);
            }

            if ($user->isSuperAdmin()) {
                return redirect()->intended(route('super-admin.dashboard'));
            }

            if ($user->canAccessBackoffice()) {
                return redirect()->intended(route('backoffice.dashboard'));
            }

            if ($user->isStockman()) {
                return redirect()->intended(route('stockman.inventory'));
            }

            return redirect()->intended(route('pos.cashier'));
        }

        return back()->withErrors([
            'email' => 'Invalid credentials.',
        ])->onlyInput('email');
    }

    /**
     * INSA POS Android shells default to cashier POS, not web admin panels.
     */
    private function redirectInsaAndroidAfterLogin(User $user): RedirectResponse
    {
        if ($user->isStockman()) {
            return redirect()->intended(route('stockman.inventory'));
        }

        return redirect()->intended(route('pos.cashier'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
