<?php

namespace App\Http\Controllers;

use App\Models\PortalAlert;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * A signed-in user is done with an alert (its popup or its row), and loads an alert's image.
 */
class PortalAlertController extends Controller
{
    public function dismiss(Request $request, PortalAlert $portalAlert): RedirectResponse|JsonResponse
    {
        $portalAlert->dismissFor($request->user());

        // The portal dismisses in the background, so leaving the page right after cannot cancel it.
        return $request->expectsJson() ? response()->json(['dismissed' => true]) : back();
    }

    /**
     * The image of an alert, for signed-in users. Kept on the private disk, as there is no public
     * storage link; browsers may keep it, as the URL changes with every new upload.
     */
    public function image(PortalAlert $portalAlert): StreamedResponse
    {
        abort_unless($portalAlert->image_path && Storage::exists($portalAlert->image_path), 404);

        return Storage::response($portalAlert->image_path, null, ['Cache-Control' => 'private, max-age=604800']);
    }
}
