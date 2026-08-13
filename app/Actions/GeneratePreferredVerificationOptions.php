<?php

namespace App\Actions;

use Laravel\Passkeys\Actions\GenerateVerificationOptions;
use Laravel\Passkeys\Contracts\PasskeyUser;
use Laravel\Passkeys\Passkeys;
use ParagonIE\ConstantTime\Base64UrlSafe;
use Webauthn\AuthenticatorSelectionCriteria;
use Webauthn\PublicKeyCredentialDescriptor;
use Webauthn\PublicKeyCredentialRequestOptions;

class GeneratePreferredVerificationOptions extends GenerateVerificationOptions
{
    public function __invoke(?PasskeyUser $user = null): PublicKeyCredentialRequestOptions
    {
        return PublicKeyCredentialRequestOptions::create(
            challenge: random_bytes(32),
            rpId: Passkeys::relyingPartyId(),
            allowCredentials: $this->allowCredentials($user),
            userVerification: AuthenticatorSelectionCriteria::USER_VERIFICATION_REQUIREMENT_PREFERRED,
            timeout: Passkeys::timeout(),
        );
    }

    public function allowCredentials(?PasskeyUser $user): array
    {
        if (! $user instanceof PasskeyUser) {
            return [];
        }

        $type = PublicKeyCredentialDescriptor::CREDENTIAL_TYPE_PUBLIC_KEY;

        return $user->passkeys()->get()->map(
            fn ($passkey): PublicKeyCredentialDescriptor => PublicKeyCredentialDescriptor::create(
                $type,
                Base64UrlSafe::decodeNoPadding($passkey->credential_id)
            )
        )->all();
    }
}