# Sign-in, registration and onboarding in the new portal design

Date: 2026-11-08 · Branch: `feature/onboarding-ui` (on top of `feature/onboarding-improvements`)

## Intent

Give sign-in, registration and onboarding the look of the new portal design (Spacepad Portal
Prototype, screens 09 Sign in, 10 Onboarding and 11 First display live), built with Inertia and Vue 3.
Only what Spacepad can do today: no demo workspace, no IT consent email, no merging of accounts or
auto-join, no Console waitlist. Everything after onboarding stays Blade for now.

The onboarding questions move to the front, as in the design, and every step can be skipped.

## Decisions

- **Stack.** `inertiajs/inertia-laravel` 2 and `@inertiajs/vue3` with Vue 3 (same as HQ). A separate Vite
  entry (`resources/js/inertia.js`, `resources/css/inertia.css`) so the Blade pages are untouched. The
  root view keeps everything the Blade layout loads on the funnel: GTM, reCAPTCHA, impersonation banner.
- **Design tokens** from the Spacepad Design System as a Tailwind v4 `@theme` (navy `#14213D`, amber
  `#FDA312`, ground `#F5F7FA`, slate text scale). Inter is self-hosted through `@fontsource-variable/inter`
  instead of Google Fonts, in line with the privacy positioning.
- **Sign in and register are one page** with two tabs. Registering asks only the email address; the
  name moves to step 1 of the wizard. The "check your inbox" state replaces the form after sending.
- **Wizard steps:** Workspace, About you, Products, Calendar, Room, Tablet, Ready. Team is left out until
  merging and auto-join exist.
  1. Workspace: business or personal, your name, workspace name, terms (self-hosted only).
  2. About you: role, current system, number of rooms, where the calendars live.
  3. Products: Display, Board (Pro), Console (coming). An answer only; nothing is prepared.
  4. Calendar: connect Microsoft, Google or CalDAV through the existing flows, read-only by default,
     with the provider from step 2 suggested. The OAuth callbacks return to the wizard.
  5. Room: pick a room (or calendar) from the connected account and create the first display through
     the existing display creation.
  6. Tablet: the connect code; the page polls and continues once a tablet has paired.
  7. Ready, then the "First display live" screen with the next steps and the way to the dashboard.
  Personal workspaces skip steps 2 and 3.
- **Answers** go to `onboarding_answers` with source `onboarding`. A question left open on a submitted
  step is stored as `skipped`. New question keys: `role`, `calendar_provider`, `products`.
- **Funnel.** `funnel_events` records `onboarding_step_viewed`, `onboarding_step_completed` and
  `onboarding_step_skipped` (with the step in `properties`), and `onboarding_completed` /
  `onboarding_skipped`.
- **Progress.** A new `users.onboarding_completed_at` marks the end of the wizard, so connecting a
  calendar halfway no longer sends someone to the dashboard. Existing users with a usage type are
  backfilled as completed. "Skip setup" sets it too.
- **Resuming.** The wizard opens at the first step that is not done yet; `?step=` lets the back button
  and the OAuth return pick a step.

## Left out

Demo workspace, IT consent email, Team step, Join existing workspace (06), Console waitlist,
"continues automatically" after the magic link, the rest of the portal.
