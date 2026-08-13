<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\OtpLoginService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class OtpLoginController extends Controller
{
    public function requestOtp(Request $request, OtpLoginService $otpLoginService): RedirectResponse
    {
        $data = $request->validate([
            'identifier' => 'required|string|max:255',
            'trust_device' => 'nullable|boolean',
        ]);

        $user = $otpLoginService->findUser($data['identifier']);

        if (! $user) {
            return back()
                ->withErrors(['otp_identifier' => 'We could not find an account for that email address or mobile number.'])
                ->withInput();
        }

        if ($otpLoginService->trustedDeviceMatchesUser($request, $user)) {
            auth()->login($user, true);

            return $this->redirectAfterLogin($request, $user);
        }

        $recipient = $otpLoginService->issue($data['identifier']);

        if (! $recipient) {
            return back()
                ->withErrors(['otp_identifier' => 'This account does not have a usable email address or mobile number for OTP login.'])
                ->withInput();
        }

        session([
            'otp_login' => [
                'user_id' => $recipient['user']->id,
                'identifiers' => $recipient['verification_identifiers'],
                'delivery_summary' => $otpLoginService->describeDestinations($recipient['deliveries']),
                'remember_device' => (bool) ($data['trust_device'] ?? false),
            ],
        ]);

        return redirect()
            ->route('login.otp.challenge')
            ->with('status', 'We sent a one-time code to '.$otpLoginService->describeDestinations($recipient['deliveries']).'.');
    }

    public function showChallenge(Request $request, OtpLoginService $otpLoginService)
    {
        $pending = $request->session()->get('otp_login');

        if (! $pending || empty($pending['user_id']) || empty($pending['identifiers'])) {
            return redirect()->route('login');
        }

        return view('auth.otp-challenge', [
            'deliverySummary' => $pending['delivery_summary'] ?? 'your registered contact',
        ]);
    }

    public function verifyOtp(Request $request, OtpLoginService $otpLoginService): RedirectResponse
    {
        $request->validate([
            'code' => 'required|string|size:6',
        ]);

        $pending = $request->session()->get('otp_login');

        if (! $pending || empty($pending['user_id']) || empty($pending['identifiers'])) {
            return redirect()->route('login')->withErrors([
                'code' => 'Your login code expired. Please request a new code.',
            ]);
        }

        $user = User::find($pending['user_id']);
        if (! $user) {
            $request->session()->forget('otp_login');

            return redirect()->route('login')->withErrors([
                'code' => 'We could not complete this login request.',
            ]);
        }

        if (! $otpLoginService->verifyIssuedCode($user, $pending['identifiers'], $request->input('code'))) {
            return back()->withErrors([
                'code' => 'Invalid or expired OTP. Please try again.',
            ]);
        }

        auth()->login($user, true);
        $request->session()->forget('otp_login');

        $response = $this->redirectAfterLogin($request, $user);

        if (! empty($pending['remember_device'])) {
            $cookieValue = $otpLoginService->createTrustedDevice($user, $request);
            $response->cookie(
                OtpLoginService::TRUSTED_DEVICE_COOKIE,
                $cookieValue,
                $otpLoginService->trustedDeviceCookieMinutes(),
                '/',
                null,
                $request->isSecure(),
                true,
                false,
                'lax'
            );
        }

        return $response;
    }

    public function resendOtp(Request $request, OtpLoginService $otpLoginService): RedirectResponse
    {
        $pending = $request->session()->get('otp_login');

        if (! $pending || empty($pending['identifiers'])) {
            return redirect()->route('login');
        }

        $recipient = $otpLoginService->issue($pending['identifiers'][0]);

        if (! $recipient) {
            $request->session()->forget('otp_login');

            return redirect()->route('login')->withErrors([
                'identifier' => 'We could not resend the login code. Please start again.',
            ]);
        }

        $request->session()->put('otp_login.user_id', $recipient['user']->id);
        $request->session()->put('otp_login.identifiers', $recipient['verification_identifiers']);
        $request->session()->put('otp_login.delivery_summary', $otpLoginService->describeDestinations($recipient['deliveries']));

        return back()->with('status', 'A fresh one-time code was sent to '.$otpLoginService->describeDestinations($recipient['deliveries']).'.');
    }

    private function redirectPathFor(User $user): string
    {
        if ($user->isAdmin()) {
            return route('admin.dashboard');
        }

        return route('account.dashboard');
    }

    private function redirectAfterLogin(Request $request, User $user): RedirectResponse
    {
        $fallback = $this->redirectPathFor($user);
        $intended = $request->session()->pull('url.intended');

        if (! $intended || (! $user->isAdmin() && $this->isAdminUrl($intended))) {
            return redirect()->to($fallback);
        }

        return redirect()->to($intended);
    }

    private function isAdminUrl(string $url): bool
    {
        $path = parse_url($url, PHP_URL_PATH) ?: $url;

        return str_starts_with('/' . ltrim($path, '/'), '/admin');
    }
}
