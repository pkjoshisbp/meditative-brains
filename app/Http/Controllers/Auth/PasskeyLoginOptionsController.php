<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\OtpLoginService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Laravel\Passkeys\Actions\GenerateVerificationOptions;
use Laravel\Passkeys\Support\WebAuthn;

class PasskeyLoginOptionsController extends Controller
{
    public function __invoke(
        Request $request,
        GenerateVerificationOptions $generate,
        OtpLoginService $otpLoginService,
    ): JsonResponse {
        $data = $request->validate([
            'identifier' => ['required', 'string', 'max:255'],
        ]);

        $user = $otpLoginService->findUser($data['identifier']);

        if (! $user || ! $user->passkeys()->exists()) {
            throw ValidationException::withMessages([
                'identifier' => 'No passkey is registered for that account. Enter the same email, username, or mobile number you used when creating the passkey, or sign in with your password first and add a new passkey.',
            ]);
        }

        $options = $generate($user);
        $serialized = WebAuthn::toJson($options);

        $request->session()->put('passkey.verification_options', $serialized);

        return response()->json([
            'options' => WebAuthn::toBrowserArray($options),
        ]);
    }
}