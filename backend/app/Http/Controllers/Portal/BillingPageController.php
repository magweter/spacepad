<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Support\PortalShell;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BillingPageController extends Controller
{
    public function index(Request $request): Response
    {
        $workspace = $request->user()->getSelectedWorkspace();
        abort_unless($workspace !== null, 404);

        return Inertia::render('Billing/Index', [
            'shell' => PortalShell::for($request, $workspace, 'billing'),
        ]);
    }
}
