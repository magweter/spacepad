<?php

namespace App\Http\Controllers\Auth;

use App\Enums\OAuthDriver;
use App\Http\Requests\Auth\OAuth2TokenRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class GoogleController extends SocialAuthController
{
    protected string $driver = OAuthDriver::GOOGLE;

    public function token(OAuth2TokenRequest $oauthTokenRequest): RedirectResponse
    {
        return parent::token($oauthTokenRequest);
    }

    public function redirect(): mixed
    {
        return parent::redirect();
    }

    public function callback(): RedirectResponse
    {
        return parent::callback();
    }
}
