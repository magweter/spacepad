<?php

namespace App\Http\Controllers;

use App\Models\PortalAlert;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * A signed-in user closes the popup of an alert; it stays a row in the portal while it runs.
 */
class PortalAlertController extends Controller
{
    public function dismiss(Request $request, PortalAlert $portalAlert): RedirectResponse
    {
        $portalAlert->dismissFor($request->user());

        return back();
    }
}
