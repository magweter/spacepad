<?php

namespace App\Support;

use App\Models\RoadmapItem;
use App\Models\RoadmapVote;
use App\Models\User;

/**
 * The help content and roadmap shown in the portal's "Help & Roadmap" panel. One source for the
 * Blade slide-over and the Vue HelpPanel (via HelpController).
 */
class HelpArticles
{
    /**
     * FAQ entries: id, category, categoryLabel, accentColor (used by the Blade panel only),
     * question and answer.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function faqs(): array
    {
        return [
            [
                'id' => 1,
                'category' => 'setup',
                'categoryLabel' => 'Getting started',
                'accentColor' => '#3b82f6',
                'question' => 'How do I connect my first display?',
                'answer' => 'Download the Spacepad app on your tablet from the Play Store or App Store. Open it and enter the connect code. You find it on the Overview under "Pair a tablet", and in the panel of any display on the Displays page. The tablet shows up there as soon as it is connected.',
            ],
            [
                'id' => 2,
                'category' => 'setup',
                'categoryLabel' => 'Getting started',
                'accentColor' => '#3b82f6',
                'question' => 'Where do I find my connect code?',
                'answer' => 'On the Overview under "Pair a tablet", and in the panel of any display on the Displays page. The code belongs to the workspace you have open. If you don\'t see it, set up a display first with "New display".',
            ],
            [
                'id' => 3,
                'category' => 'm365',
                'categoryLabel' => 'Microsoft 365',
                'accentColor' => '#8b5cf6',
                'question' => 'My display shows the organizer\'s name instead of the meeting title',
                'answer' => 'This is common behaviour, as it\'s a Microsoft default. In order to change this, you need to change a Exchange Online setting of your room mailbox. It\'s Microsoft default, not a Spacepad issue. You\'ll need Exchange admin access to run this command. The only way to change this is by using PowerShell. Open PowerShell and run: Set-CalendarProcessing -Identity "room@yourdomain.com" -AddOrganizerToSubject $false -DeleteSubject $false',
            ],
            [
                'id' => 4,
                'category' => 'm365',
                'categoryLabel' => 'Microsoft 365',
                'accentColor' => '#8b5cf6',
                'question' => 'New meetings aren\'t showing up on my display',
                'answer' => 'First, check that the right room or calendar is connected (open display settings). If it looks correct, try disconnecting and reconnecting your Microsoft account. This refreshes the webhook that notifies Spacepad of changes in real time. Events should appear within a few minutes.',
            ],
            [
                'id' => 5,
                'category' => 'm365',
                'categoryLabel' => 'Microsoft 365',
                'accentColor' => '#8b5cf6',
                'question' => 'Our IT admin needs to approve Spacepad\'s Microsoft 365 permissions',
                'answer' => 'For write (booking) access to M365 room resources, your Azure AD admin needs to grant admin consent for the Spacepad app. A link to the admin consent flow is shown during the Microsoft connection process, so you can copy it and forward it to your admin. For read-only access, regular user consent is usually enough.',
            ],
            [
                'id' => 6,
                'category' => 'google',
                'categoryLabel' => 'Google',
                'accentColor' => '#ef4444',
                'question' => 'My Google Workspace room calendars aren\'t syncing',
                'answer' => 'Make sure you connected a Google Workspace account (not a personal Gmail) and granted write permission during the connection flow. Also check that the room resources are accessible by the connected account. If you\'re using a service account, verify domain-wide delegation is configured in your Google Admin console.',
            ],
            [
                'id' => 7,
                'category' => 'google',
                'categoryLabel' => 'Google',
                'accentColor' => '#ef4444',
                'question' => 'When do I need a service account for Google Workspace?',
                'answer' => 'A service account is the right choice when your Google Workspace admin doesn\'t want to grant a personal account access to room resources, or when you\'re managing many rooms at scale. It uses domain-wide delegation to access calendars on behalf of users. If you\'re unsure, start with a regular user account, you can switch later without losing your display setup.',
            ],
            [
                'id' => 8,
                'category' => 'display',
                'categoryLabel' => 'Display',
                'accentColor' => '#10b981',
                'question' => 'My display shows "No upcoming events" but meetings exist',
                'answer' => 'A few common causes: the connected calendar differs from the one actually used to book the room; the first sync can take a few minutes after initial setup; or the account connection may have expired. Check that the account shows "Connected" on the Calendars page and try pulling to refresh on the display app.',
            ],
            [
                'id' => 9,
                'category' => 'display',
                'categoryLabel' => 'Display',
                'accentColor' => '#10b981',
                'question' => 'Can I book a room directly from the display tablet?',
                'answer' => 'Yes, on-display booking is a Pro feature and requires a connected account with write permissions. Once both are active, a booking button appears on the display. You can configure the default booking duration and toggle the feature per display in its settings.',
            ],
            [
                'id' => 10,
                'category' => 'display',
                'categoryLabel' => 'Display',
                'accentColor' => '#10b981',
                'question' => 'My tablet keeps going to sleep or showing a screensaver',
                'answer' => 'This is a tablet OS setting, not Spacepad. On Android: set screen timeout to "Never" and disable adaptive brightness. On iPad: go to Settings → Display & Brightness → Auto-Lock → Never. For permanent kiosk use, also look into Android\'s guided access or a dedicated kiosk launcher app to keep the display locked to Spacepad.',
            ],
            [
                'id' => 17,
                'category' => 'display',
                'categoryLabel' => 'Display',
                'accentColor' => '#10b981',
                'question' => 'Meetings booked via the tablet don\'t appear in the calendar',
                'answer' => 'This is almost always a permissions issue. Spacepad can only write to your calendar if the connected account has write access. Check the following: (1) Read-only connection: if your Microsoft or Google account was connected with read-only permissions, Spacepad can show events but cannot create them. Go to the Calendars page and reconnect the account, making sure to grant write (read & write) access when prompted. (2) Microsoft 365 room resources: room mailboxes in Exchange Online require admin consent before a third-party app can book on their behalf. Ask your IT admin to approve write permissions for Spacepad via the admin consent link shown during the connection flow. (3) Google Workspace: if you use a service account, verify that domain-wide delegation includes write scope (https://www.googleapis.com/auth/calendar). (4) CalDAV: confirm the connected account has write permissions on the specific calendar, not just read access. After fixing permissions, reconnect the account on the Calendars page to apply the new access level.',
            ],
            [
                'id' => 11,
                'category' => 'display',
                'categoryLabel' => 'Display',
                'accentColor' => '#10b981',
                'question' => 'How do I prevent users from closing the app or pressing the home button on Android?',
                'answer' => 'Android has two built-in options. The easiest is Screen Pinning: go to Settings → Security → Screen Pinning (or "Pin windows"), enable it, then open Spacepad, tap the Recents button and tap the pin icon. Users cannot switch apps without a PIN. For a stricter lockdown, use Android\'s Lock Task Mode via a Mobile Device Management (MDM) solution such as Scalefusion, ManageEngine, or Google\'s zero-touch enrollment. This fully disables the home button, recents, and notifications, which is ideal for permanent room displays. You can also enable "Hide admin actions" in Spacepad\'s display settings to prevent users from logging out or switching rooms from within the app.',
            ],
            [
                'id' => 12,
                'category' => 'display',
                'categoryLabel' => 'Display',
                'accentColor' => '#10b981',
                'question' => 'How do I lock the app on iPad so users can\'t exit Spacepad?',
                'answer' => 'Use iOS Guided Access: it pins the iPad to Spacepad and asks for a code before anyone can leave. Setup takes about two minutes: (1) On the iPad, open Settings → Accessibility → Guided Access and turn it on. (2) Tap Passcode Settings → Set Guided Access Passcode and enter a 6-digit code twice. Write it down somewhere safe, you need it to get back out. (3) In that same screen, set Display Auto-Lock to Never. (4) Go back to Settings → Accessibility → Accessibility Shortcut (bottom of the list) and make sure only Guided Access is ticked, so the triple-click in the next step goes straight to Guided Access instead of showing a menu. (5) Open the Spacepad app and let it load the room screen. (6) Triple-click the top button (the power/sleep button, and on older iPads with a round Home button, triple-click that instead) and tap Start in the top right. The iPad is now locked to Spacepad: swiping, the app switcher and the home screen are all blocked. To get out, triple-click the same button, enter your code and tap End in the top left. One thing to know: Guided Access switches off when the iPad powers down completely, so after a battery drain you need to repeat steps 5 and 6 once. If you want it to re-lock automatically after every reboot, supervise the iPad with Apple Configurator (free Mac app, requires a one-time reset of the iPad) or manage it centrally with Apple Business Manager plus an MDM such as Jamf or Microsoft Intune, and enable Single App Mode, so Spacepad launches and locks itself on boot. Two extras for a wall-mounted iPad: set Settings → Display & Brightness → Auto-Lock → Never, and keep it on permanent power so it never runs flat. Inside Spacepad you can also enable "Hide admin actions" in the display settings, so nobody can log out or switch rooms from within the app.',
            ],
            [
                'id' => 18,
                'category' => 'display',
                'categoryLabel' => 'Display',
                'accentColor' => '#10b981',
                'question' => 'How do I assign a profile to my displays?',
                'answer' => 'Profiles are a Pro feature that let you configure several displays at once. First create a profile on the Profiles page and set what it should do, such as check-in, booking, texts, branding and advertisement. Then open the Displays page, tick the displays that should use it and assign the profile. Assigning clears the own settings of the selected displays so they fully follow the profile, and you are warned first when that affects a display. The checkboxes appear once your workspace has more than one display. Editing the profile later updates every linked display straight away. To stop following a profile, select the displays and unlink them: they keep their current settings. You can still give a single display its own values for one section on its configuration page; the other sections keep following the profile.',
            ],
            [
                'id' => 14,
                'category' => 'billing',
                'categoryLabel' => 'Billing',
                'accentColor' => '#f59e0b',
                'question' => 'What\'s included in the free plan?',
                'answer' => 'The free plan includes 1 display with real-time calendar sync and basic event viewing, enough to fully test with your first room. Booking, multiple displays, boards, check-in, and display customization all require Pro.',
            ],
            [
                'id' => 15,
                'category' => 'billing',
                'categoryLabel' => 'Billing',
                'accentColor' => '#f59e0b',
                'question' => 'What\'s included in Pro?',
                'answer' => 'Pro unlocks unlimited displays, meeting boards (multi-room overview screens for lobbies or hallway TVs), on-display room booking, check-in functionality, full display customization (logo, colors, themes), and priority support. See spacepad.io/pricing for full details.',
            ],
            [
                'id' => 16,
                'category' => 'billing',
                'categoryLabel' => 'Billing',
                'accentColor' => '#f59e0b',
                'question' => 'What happens when my trial ends?',
                'answer' => 'Your account reverts to the free plan. No data is lost and nothing is deleted. Your first display keeps working. Any additional displays will show a "subscription required" message until you upgrade. You can upgrade any time from the Billing page.',
            ],
        ];
    }

    /**
     * FAQ categories in display order: id, label, color (used by the Blade panel only).
     *
     * @return array<int, array<string, string>>
     */
    public static function categories(): array
    {
        return [
            ['id' => 'setup',   'label' => 'Getting started', 'color' => '#3b82f6'],
            ['id' => 'm365',    'label' => 'Microsoft 365',   'color' => '#8b5cf6'],
            ['id' => 'google',  'label' => 'Google',           'color' => '#ef4444'],
            ['id' => 'display', 'label' => 'Display',          'color' => '#10b981'],
            ['id' => 'billing', 'label' => 'Billing',          'color' => '#f59e0b'],
        ];
    }

    /**
     * Approved roadmap items in roadmap order, with vote counts and whether the given user voted.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function roadmapItems(?User $user): array
    {
        $userVotedIds = $user
            ? RoadmapVote::where('user_id', $user->id)->pluck('roadmap_item_id')->all()
            : [];

        return RoadmapItem::approved()
            ->ordered()
            ->withCount('votes')
            ->get()
            ->map(fn (RoadmapItem $item) => [
                'id' => $item->id,
                'title' => $item->title,
                'description' => $item->description,
                'status' => $item->status->value,
                'statusLabel' => $item->status->label(),
                'statusClass' => $item->status->badgeClass(),
                'isPulse' => $item->status->value === 'building',
                'expectedAt' => $item->expected_at?->format('M Y'),
                'votesCount' => $item->votes_count,
                'voted' => in_array($item->id, $userVotedIds),
            ])
            ->values()
            ->all();
    }
}
