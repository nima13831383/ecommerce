<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\AuthOtpChallenge;
use App\Services\Auth\CustomerOtpService;
use App\Services\Settings\SettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(Request $request, SettingsService $settings, CustomerOtpService $otp): View
    {
        $customerAuthMode = $settings->get('auth.customer_auth_mode');
        $resendState = null;

        if ($customerAuthMode === 'sms_otp') {
            $resendState = $otp->resendState(
                $request->session()->get('auth.otp.login_challenge_id'),
                $request->session()->get('auth.otp.login_mobile'),
                AuthOtpChallenge::PURPOSE_LOGIN,
            );

            if ($request->session()->has('auth.otp.login_mobile') && $resendState === null) {
                $request->session()->forget(['auth.otp.login_mobile', 'auth.otp.login_challenge_id']);
            }
        }

        return view('auth.login', compact('customerAuthMode', 'resendState'));
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        if (app(SettingsService::class)->get('auth.customer_auth_mode') !== 'email_password') {
            abort(404);
        }

        $request->authenticate();

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
