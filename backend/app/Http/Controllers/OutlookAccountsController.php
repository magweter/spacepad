<?php

namespace App\Http\Controllers;

use App\Services\OutlookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class OutlookAccountsController extends Controller
{
    protected OutlookService $outlookService;

    public function __construct(OutlookService $outlookService)
    {
        $this->outlookService = $outlookService;
    }

    public function auth(): RedirectResponse
    {
        return redirect($this->outlookService->getAuthUrl());
    }

    /**
     * @throws \Exception
     */
    public function callback(): RedirectResponse
    {
        $authCode = request('code');
        $this->outlookService->authenticateOutlookAccount($authCode);

        return redirect()->route('dashboard');
    }
}
