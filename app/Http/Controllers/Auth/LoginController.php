<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\OtpLoginService;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Login Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles authenticating users for the application and
    | redirecting them to your home screen. The controller uses a trait
    | to conveniently provide its functionality to your applications.
    |
    */

    use AuthenticatesUsers;

    /**
     * Where to redirect users after login.
     *
     * @var string
     */
    protected $redirectTo = '/my-account';

    /**
     * Determine the post-login redirect path for the authenticated user.
     */
    protected function redirectTo(): string
    {
        /** @var User|null $user */
        $user = auth()->user();

        if ($user && $user->isAdmin()) {
            return route('admin.dashboard');
        }

        return route('account.dashboard');
    }

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('guest')->except('logout');
        $this->middleware('auth')->only('logout');
    }

    protected function validateLogin(Request $request): void
    {
        $request->validate([
            'email' => 'required|string',
            'password' => 'required|string',
        ]);
    }

    protected function attemptLogin(Request $request): bool
    {
        $identifier = trim((string) $request->input('email'));
        $user = app(OtpLoginService::class)->findUser($identifier);

        if (! $user || ! Hash::check((string) $request->input('password'), (string) $user->password)) {
            return false;
        }

        $this->guard()->login($user, $request->boolean('remember'));

        return true;
    }

    protected function sendFailedLoginResponse(Request $request)
    {
        throw ValidationException::withMessages([
            'email' => [trans('auth.failed')],
        ]);
    }
}
