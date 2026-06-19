<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    public function showLoginForm(Request $request)
    {
        $errorCode = $request->query('error') ?? $request->session()->pull('login_error');

        return view('auth.login', [
            'loginError'   => login_error_message(is_string($errorCode) ? $errorCode : null),
            'isAndroidApp' => is_insa_android_app($request),
        ]);
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        $remember = $request->boolean('remember') || is_insa_android_app($request);

        if (Auth::attempt($credentials, $remember)) {
            $request->session()->regenerate();

            $user = Auth::user();

            if (is_epayplus_product($request)) {
                if ($user->isSuperAdmin() || in_array($user->role, ['owner', 'admin'], true)) {
                    return redirect()->intended(route('epayplus.dashboard'));
                }

                abort(403, 'This account is not authorized for ePay Plus Admin.');
            }

            if (is_insa_product($request) && is_insa_android_app($request)) {
                return $this->redirectInsaAndroidAfterLogin($user, $request);
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
    private function redirectInsaAndroidAfterLogin(User $user, Request $request): RedirectResponse
    {
        $target = $user->isStockman()
            ? route('stockman.inventory', absolute: false)
            : route('pos.cashier', absolute: false);

        return redirect()->intended($request->getSchemeAndHttpHost().$target);
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
