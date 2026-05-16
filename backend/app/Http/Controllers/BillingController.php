<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Spatie\GoogleTagManager\GoogleTagManagerFacade;

class BillingController extends Controller
{
    public function thanks(): RedirectResponse
    {
        GoogleTagManagerFacade::flashPush([
            'event' => 'purchase',
        ]);

        if (config('services.google_conversion.send_to')) {
            GoogleTagManagerFacade::flashPush([
                'event' => 'conversion',
                'send_to' => config('services.google_conversion.send_to'),
                'value' => config('services.google_conversion.value'),
                'currency' => config('services.google_conversion.currency'),
                'transaction_id' => '',
            ]);
        }

        return redirect()->route('dashboard');
    }
}
