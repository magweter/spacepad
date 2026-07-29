# Release notes: v1.8.0

## New features

### Advertisement overlay on tablets
Displays can now show a configurable image overlay between meetings. The interval and duration are set per-display in the web portal. The overlay auto-dismisses on image load errors and is fully toggleable — disabling it prevents empty panels from rendering.

### Meeting extend
Users can extend the current ongoing meeting directly from the tablet by +15, +30, or +60 minutes. The extend button appears in the action panel next to Cancel. Controlled per-display by an `extend_enabled` toggle in the web portal. Works with Outlook, Google, and CalDAV when write permissions are available.

### Custom booking with description and attendees
When booking a room from the tablet, users can now add a meeting description and invite attendees by email address. Supported across Outlook, Google, and CalDAV.

### Join-meeting button on boards
Boards can optionally show a "Join meeting" button on cards when the current event has a Teams, Zoom, or Google Meet link. Configurable per board in settings.

### Multiple display view modes on the tablet
The tablet display now supports multiple schedule/timeline modes:
- A day timeline widget with vertical time blocks, previous/next navigation, date picker, overlapping event grouping, and a current-time indicator
- An optional side panel layout
- Organizer names and join URLs are shown on events

### Microsoft 365 booking method choice
Per-display choice between `user_account` (delegated, existing) and `admin_consent` (app-only token) for reading and booking room calendars.

### Public board URLs
Boards can be made publicly accessible — anyone with the link can view the board without logging in. Useful for lobby screens and shared TVs. A shareable URL is generated on first save, shown in a copy modal, and the modal auto-opens when public access is first enabled.

### Public roadmap with voting
The dashboard now includes a public roadmap with upvote support, a Help/FAQ section, a support request form, and a link to open diagnostics — all accessible from the main navigation.

### Calendar sync diagnostics tool
A step-by-step pipeline view for troubleshooting missing events, accessible via the checklist icon on each display row. Shows:

1. Calendar configuration (provider, calendar ID, room vs user calendar)
2. Account auth health (token expiry, refresh token status)
3. Raw events from the external API
4. Server-side filter breakdown (all-day filtered, released)
5. Events actually delivered to the tablet
6. Webhook subscription status

---

## Improvements

### Clearer connectivity and error states
Periodic background refresh failures are now silent — instead of repeated error toasts, a subtle amber "Possibly outdated" chip appears in the bottom bar. Tapping it triggers a manual refresh. User-initiated actions (booking, cancel, extend) surface the actual server error message when available, fall back to a connectivity check, then a generic message. SSL-specific errors are surfaced with a dedicated message.

### Stale data indicator
The tablet shows a visual indicator when displayed data may be outdated, replacing the previous noisy toast-based approach.

### Magic links valid for 24 hours
Login magic links now remain valid for 24 hours, up from the previous shorter window.

### Docker image improvements
Reduced image size, upgraded build tooling (Node.js, pnpm), and improved healthcheck. Git commit SHA in image tags is capped at 7 characters. `latest` and `stable` tags are only published on version tag builds.

---

## Bug fixes

- **Microsoft 365 Admin Consent rooms**: events were not loading after the v1.7.0 booking method update (*"The specified object was not found in the store"*). The read path now correctly uses an app-only token for Admin Consent rooms.
- **Subscription renewal job**: a single bad subscription (e.g. invalid resource URL returning HTTP 400) no longer crashes the entire renewal run — errors are caught per-subscription, logged, and the job continues.
- **Outlook event timezones**: events were parsing with incorrect times due to missing UTC normalisation. Fixed by adding the `Prefer: outlook.timezone="UTC"` header and normalising datetime strings.
- **Board check-in status**: the board now shows a dedicated "Check-in" badge (amber) separate from "Transitioning", with correct cache invalidation so status updates are reflected promptly.
- **Board clock locale**: the header clock on board pages now uses the board's configured language — English boards show 12-hour format, other locales use their native convention.
