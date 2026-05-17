# Spacepad — Feature Overview

A privacy-focused room display app that shows real-time room availability, synced with your existing calendar.

---

## Calendar Integrations

- **Microsoft 365 / Exchange Online** — connect via OAuth with admin consent; read-only or read/write
- **Google Workspace** — connect via OAuth or service account with domain-wide delegation; read-only or read/write
- **CalDAV** — generic CalDAV support for self-hosted and third-party calendar servers
- Real-time sync with webhook-based push updates
- Manual refresh available from the tablet at any time

---

## Tablet Display

### Room Status
- Color-coded status at a glance: **green** (available), **red** (reserved), **amber** (transitioning / check-in required)
- Fully customizable state labels per display (e.g. "All yours!", "Keep it short!", "In a meeting")
- Current meeting title (can be hidden for privacy)
- Meeting organizer name (optional, per display)
- Time remaining in current meeting
- Time until next meeting
- Next upcoming event preview

### Clock & Identity
- Real-time digital clock
- Room name displayed prominently
- Custom logo support

### Booking
- Book the room directly from the tablet
- Duration options: 15, 30, or 60 minutes, plus a custom duration picker
- Future bookings: optionally allow booking rooms for other days
- Bookings sync back to the connected calendar (requires write permission)
- Unavailable time slots shown as disabled

### Extend Meeting
- Extend an active meeting by +15, +30, or +60 minutes directly from the tablet
- Syncs the change back to the external calendar

### End Meeting
- End the current meeting early from the tablet
- Configurable cancel permissions: everyone, tablet-booked meetings only, or nobody

### Check-In
- Require attendees to check in before a meeting starts
- Configurable check-in window (1–60 minutes before start)
- Configurable grace period (1–30 minutes after start) before the room is released
- Tablet-booked meetings are exempt from check-in automatically

### Schedule Display
Four modes to show today's schedule — one per display:
- **Disabled** — no schedule shown
- **Side panel** — slides in from the side when a button is tapped
- **Inline** — always-visible timeline embedded within the display, vertically centered alongside the content
- **Full-height panel** — always-visible timeline spanning the full height of the right side of the display
- **View schedule button** — opens a full-day modal overlay (can be combined with any of the above)

The schedule view shows all events for today, tomorrow, and yesterday.

### Advertisement Display
- Show a custom image advertisement on the right half of the screen
- Configurable interval (how often it appears) and duration (how long it stays)
- Dismissed with a tap; disappears automatically after the configured duration

### Stale Data & Connectivity Indicators
- Stale data warning when the display hasn't synced recently
- Distinction between **no internet** and **server unreachable**
- Tap the indicator to manually trigger a refresh

---

## Display Settings (Pro)

All settings are per-display and managed from the web portal.

| Setting | Options |
|---|---|
| Check-in | On/off, window (minutes), grace period (minutes) |
| Booking | On/off |
| Future bookings | On/off |
| Schedule display | Disabled / Side panel / Inline / View schedule button |
| Extend meeting | On/off |
| Cancel permission | Everyone / Tablet bookings only / Nobody |
| Show organizer | On/off |
| Show meeting title | On/off |
| Hide admin actions | On/off |
| Border thickness | Small / Medium / Large |

### Admin Action Lockdown
- Optionally hide the switch-room and logout buttons from the tablet UI
- When hidden, admins can still access them by long-pressing the room name — they appear for 30 seconds then auto-hide
- Full kiosk lockdown via Android Screen Pinning, Android Lock Task Mode (MDM), or iOS Guided Access

---

## Display Customization (Pro)

### Branding
- Upload a custom **logo** (shown on the display)
- Upload a custom **background image** or choose from built-in backgrounds
- Choose a **font family**: Inter, Roboto, Open Sans, Lato, Poppins, Montserrat

### State Text
- Customize the label shown in each room state:
  - Available
  - Transitioning (meeting starting soon)
  - Reserved
  - Check-in required

### Advertisement
- Upload a custom advertisement image (half-screen, right side)
- Set interval and display duration

---

## Meeting Boards (Pro)

- Multi-room overview screens showing status of multiple rooms at once
- Configurable room selection per board
- Designed for lobby or hallway displays

---

## Web Portal

- **Dashboard** — overview of all connected displays and their current status
- **Display settings** — per-display behavior configuration
- **Display customization** — branding and visual configuration
- **Display diagnostics** — connection health, last sync, troubleshooting info
- **Calendar accounts** — connect and manage Google, Microsoft, and CalDAV accounts
- **Boards** — create and manage multi-room overview boards
- **Usage / analytics** — display activity statistics
- **Roadmap** — public feature roadmap with community voting and status tracking
- **Help / FAQ** — searchable help center covering setup, integrations, troubleshooting, and billing

---

## Internationalization

The tablet app is fully localized in:

- English
- Dutch
- French
- Spanish
- German
- Swedish

---

## Platform Support

- **Tablet app**: iOS and Android
- **Web portal**: all modern browsers
- Responsive tablet UI adapts to phone, portrait, and landscape orientations
- Font and layout scaling based on device dimensions

---

## Plans

### Free
- 1 display
- Real-time calendar sync
- Basic event viewing

### Pro
- Unlimited displays
- All display settings (check-in, booking, extend, cancel permissions, organizer, etc.)
- Full customization (logo, background, fonts, state text, advertisement)
- Future bookings
- Meeting boards
- Priority support
