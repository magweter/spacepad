<?php

namespace App\Http\Controllers;

use App\Models\SupportMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class SupportController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'message' => 'required|string|min:10|max:2000',
        ]);

        $user = auth()->user();

        SupportMessage::create([
            'user_id' => $user->id,
            'message' => $validated['message'],
        ]);

        try {
            Mail::raw(
                "Question from {$user->name} ({$user->email}):\n\n{$validated['message']}",
                fn ($mail) => $mail
                    ->to('m.wetering@cbyte.nl')
                    ->subject("Spacepad question — {$user->name}")
                    ->replyTo($user->email, $user->name)
            );
        } catch (\Exception) {
            // Email failure doesn't block the user; message is stored in DB
        }

        return back()->with('support_sent', true);
    }
}
