<?php

namespace App\Http\Controllers\Auth;

use App\Models\User;
use App\Services\Authentication\OidcService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Throwable;

class OidcController
{
    public function __construct(private readonly OidcService $oidc)
    {
    }

    public function login(): RedirectResponse
    {
        return redirect()->away($this->oidc->authorizationUrl());
    }

    public function callback(): RedirectResponse
    {
        try {
            $result = $this->oidc->authenticate(
                (string) request('state'),
                (string) request('code'),
            );

            $claims = $result['claims'];
            $issuer = rtrim((string) config('services.oidc.issuer'), '/');
            $subject = (string) $claims['sub'];

            $user = User::query()->updateOrCreate(
                [
                    'oidc_issuer' => $issuer,
                    'oidc_subject' => $subject,
                ],
                [
                    'email' => $claims['email'] ?? null,
                    'display_name' => $claims['name'] ?? ($claims['preferred_username'] ?? $subject),
                    'avatar_url' => $claims['picture'] ?? null,
                    'last_login_at' => now(),
                ],
            );

            Auth::login($user, false);
            request()->session()->regenerate();

            return redirect()->intended('/');
        } catch (Throwable $e) {
            report($e);

            return redirect('/?auth=error');
        }
    }

    public function logout(): RedirectResponse
    {
        Auth::logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();

        return redirect('/');
    }
}
