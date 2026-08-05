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
- Tap the indicator to manually trigger a refresh — this also resets the retry schedule
- While the server stays unreachable the tablet retries with increasing delays (up to 10 minutes) instead of every minute, so a whole building of tablets does not hammer a recovering server

---

## Display Settings (Pro)

Managed from the web portal on one **Configure display** screen, grouped into sections (Behavior,
Display, State texts, Branding, Advertisement). Each section either follows the display's linked
profile or holds its own values — see [Display Profiles](#display-profiles-pro).

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

Part of the same **Configure display** screen, in the Branding, State texts and Advertisement sections.

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
- Room categories — group the rooms of a board into named sections (e.g. "Downstairs", "Floor 2") by dragging them into categories in the board editor; rooms are shown per category on the board with a labeled section divider, and rooms left ungrouped appear last under "Other"
- Category order is set per board, so the same rooms can be ordered differently on each board
- Meeting organizer shown per room (card, grid and table view), using the real organizer from the calendar
- Today-only scope — the "next up" column and the "available until" text only consider bookings on the current day (in the viewer's timezone). A room whose next booking is on a later day shows "-" and "available until end of day" instead of a date-less time range that looks like it is happening today

---

## Display Profiles (Pro)

Reusable sets of display settings ("themes") so an admin can configure many displays at once
instead of one by one.

- Create, edit and delete **profiles** per workspace. A profile holds the same **sections** as an
  individual display: Behavior, Display, State texts, Branding and Advertisement — **including the
  images** (logo, background, advertisement image), so one upload covers every room that follows the
  profile. A display can still upload its own, which overrides the profile for that display only.
- **Assigning** happens from the Displays tab: select displays with checkboxes and assign a profile in
  bulk, or unlink them. Assigning clears the selected displays' own settings so they genuinely follow
  the profile; a warning first states how many displays that affects. Unlinking keeps their values.
- The Displays overview shows each display's linked profile, marked **Customised** when at least one
  section deviates.
- Linked displays inherit the profile's settings **live** — editing the profile updates every linked
  display automatically.
- **Inheritance is per section.** Each section on a display either follows the profile or has its own
  values, shown with a "Follows profile" / "Own settings" badge. Saving a section is what detaches that
  one section — the others keep following the profile. Every section has a "Follow profile" action to
  hand it back, plus "Reset all sections to profile" for the whole display.
- Deleting a profile **copies its values onto the linked displays** as their own settings, so rooms keep
  behaving exactly as they did instead of silently reverting to defaults.

---

## Team & Workspaces (Pro)

A workspace is the shared container for displays, boards, calendar accounts and settings — and the
unit that billing is based on. Colleagues work together inside one workspace instead of each keeping
a separate account with its own invisible set of displays.

### Roles

| | Owner | Admin | Member |
|---|---|---|---|
| Manage displays, boards, profiles, calendar accounts | ✓ | ✓ | ✓ |
| Rename the workspace | ✓ | ✓ | |
| Invite colleagues, withdraw invitations | ✓ | ✓ | |
| Change roles, remove members | ✓ | | |
| Billing and subscription | ✓ | | |

### Invitations
- Invite by email address as Admin or Member; ownership is never handed out by invitation
- One click from the email joins the workspace — no separate sign-up step and no second login email
- Valid for 7 days; can be re-sent (which invalidates the previous link) or withdrawn
- Someone signed in under a different address is told so, instead of silently joining the wrong account
- Removing a member takes away their access only — everything they created stays in the workspace

### Bringing existing data along
- While accepting an invitation, an invitee can move their own displays, boards, devices and calendar
  accounts into the workspace they are joining
- Their old workspace is left behind empty, and can then be deleted from the Team page

### Switching and managing workspaces
- Workspace switcher in the top bar, always visible, showing the current workspace and your role
- Rename a workspace, or create an additional one (for a second site, for example)
- Delete a leftover **empty** workspace yourself, provided you own it, you are its only member, it is
  not being billed, and you still administer another workspace

### Billing
- Billing is per workspace: displays count once, boards count double
- The unit total is held on the workspace and updated the moment a display or board is added or
  removed, so the figure on the Team page, the subscription quantity and the invoice are always the
  same number
- Adding or removing a display or board resizes the subscription straight away instead of waiting
  for the next scheduled sync, and lands on the workspace's billing history
- **No per-user charge** — inviting colleagues costs nothing
- Only the owner can start or manage a subscription; other members are pointed at the owner rather
  than shown a button that fails
- A workspace on trial is simply an activated workspace: the trial converts to a paid subscription on
  its own, so nothing on the dashboard asks anyone to buy. The workspace page carries the countdown,
  the date the subscription starts, and what the current usage costs per month
- A workspace that already has a subscription cannot start a second checkout

---

## Web Portal

- **Dashboard** — overview of all connected displays and their current status
- **Team** — invite colleagues, manage roles, and switch between workspaces
- **Display configuration** — one screen per display with Behavior, Display, State texts, Branding and
  Advertisement sections, each following its profile or holding its own values. Every option carries an
  info tooltip explaining what happens when it is switched on
- **Profiles** — reusable settings sets, assigned to displays in bulk from the Displays tab
- **Display diagnostics** — connection health, last sync, troubleshooting info, and a one-click reset of an errored calendar account's status so a fresh token is attempted
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

Plans apply per workspace. Usage is measured in units: a display counts as 1, a board as 2. There is
no charge per team member.

### Free
- 1 display
- Real-time calendar sync
- Basic event viewing
- Single user — inviting colleagues requires Pro

### Pro
- Unlimited displays
- All display settings (check-in, booking, extend, cancel permissions, organizer, etc.)
- Full customization (logo, background, fonts, state text, advertisement)
- Future bookings
- Meeting boards
- Team collaboration — shared workspaces with Owner / Admin / Member roles, at no cost per user
- Priority support
