# AGENTS.md

Instructions for AI coding agents working in this repository. See `CLAUDE.md` for the
architecture overview, conventions and per-area guidelines. This file covers **how to
actually run the project locally**, which is not obvious from the toolchain defaults on
this machine.

## Running the backend (Laravel)

Run from `backend/`. The primary command:

```bash
npx concurrently -c "#93c5fd,#c4b5fd,#fb7185,#fdba74" "php84 artisan serve" "php84 artisan queue:listen --tries=1" "php84 artisan pail --timeout=0" "pnpm run dev" --names=server,queue,logs,vite
```

This starts four processes: the web server on `http://127.0.0.1:8000`, the queue worker,
the Pail log tail, and Vite on `http://localhost:5173`.

If that fails, fall back to the Composer script (same four processes, via `php` and `npm`):

```bash
composer dev
```

Notes:

- **Use `php84`, never plain `php`.** `php84` is Herd's PHP 8.4 binary
  (`~/Library/Application Support/Herd/bin/php84`). The default `php` on this machine is
  8.2 and the backend requires 8.4+, so it fails. When running `composer dev` (which calls
  a bare `php` internally), prefix the PATH instead:
  `PATH="/opt/homebrew/opt/php@8.4/bin:$PATH" composer dev`.
- **`pnpm` needs Node >= 22.13.** The default `node` here is Herd's v18.17.0, which makes
  `pnpm` abort before it starts. Put Homebrew's Node (v23) first in PATH for the
  concurrently command: `PATH="/opt/homebrew/bin:$PATH" npx concurrently ...`. The
  `composer dev` fallback uses `npm`, which works on Node 18.
- **The live database is `backend/storage/database.sqlite`** (SQLite, already migrated).
  `backend/database/database.sqlite` is a leftover 0-byte file. Do not delete or migrate
  either without asking; `php84 artisan db:show` tells you which one is active.

Server binds to `127.0.0.1` only, so `curl http://localhost:8000` can fail where
`curl http://127.0.0.1:8000` succeeds.

## Running the Flutter app

Run from `app/`. **Always go through `fvm`:**

```bash
fvm flutter run -d <device-id>
```

List devices with `fvm flutter devices`. An iPad simulator is the closest match to a real
room display; `macos` and `chrome` also work for quick checks.

Notes:

- **Do not use the `flutter` on PATH.** It is `/Users/Shared/flutter` on the master
  channel (3.29.0-pre, Dart 3.7.0-dev) and `pub get` fails there: `google_fonts ^8.2.1`
  requires Dart `^3.10.0`. The project pins Flutter **3.44.8** via `app/.fvm`, which
  resolves fine.
- `app/.env` has `API_URL=https://app.spacepad.io` (production). Leave it alone. To point
  the app at the local backend, pick the **self hosted** option inside the app and enter
  `http://localhost:8000`. Confirm it took effect by watching the app log for
  `GET: http://localhost:8000/api/devices/me`.
- The iOS build warns that all plugins are Swift Packages while `ios/` still has CocoaPods
  integration. Harmless, only a build-time cost. Do not deintegrate without asking.
- **Previewing a panel resolution.** Room display panels are often 1024x600, which is not an iOS
  device size. Pass `--dart-define=FORCE_SIZE=1024x600` and the app lays itself out at exactly
  those logical pixels inside a letterboxed canvas, on any device:

  ```bash
  fvm flutter run -d <device-id> --dart-define=FORCE_SIZE=1024x600
  ```

  It is read in `app/lib/main.dart` and applied through the `GetMaterialApp` builder, so every
  `MediaQuery`-driven layout decision (including `_isPhone`) sees the forced size. Omit the flag
  and nothing changes. A dart-define only takes effect on a full run, not on hot reload.
- Hot reload (`r` / `R`) needs an interactive TTY. If you start `flutter run` as a
  background process, you cannot hot reload it; tell the user to run it in their own
  terminal when they want that.
