# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Spacepad is a privacy-focused room display application that shows real-time room availability, synced with calendars from Google, Microsoft, and CalDAV providers. The project consists of:

- **Frontend (Flutter app)**: Cross-platform mobile app for room displays
- **Backend (Laravel API)**: RESTful API handling authentication, calendar integration, and webhook processing

## Architecture

### Flutter App (`/app/`)
- **MVC Pattern**: Controllers handle business logic, Services manage API communication
- **State Management**: GetX for dependency injection and state management
- **Main Components**:
  - `controllers/`: Business logic controllers (DashboardController, DisplayController, LoginController)
  - `services/`: API communication services (ApiService, AuthService, EventService, DisplayService)
  - `models/`: Data models (EventModel, DisplayModel, DeviceModel, UserModel)
  - `pages/`: UI screens (LoginPage, DashboardPage, DisplayPage, SplashPage)
  - `components/`: Reusable UI components (ActionButton, EventLine, Spinner, Toast)

### Laravel Backend (`/backend/`)
- **Clean Architecture**: Controllers, Services, Models, and Data classes
- **Authentication**: Laravel Sanctum for API authentication
- **Calendar Integration**: Google Calendar API, Microsoft Graph API, CalDAV
- **Main Components**:
  - `app/Http/Controllers/API/`: API controllers for mobile app
  - `app/Services/`: Business logic services (EventService, GoogleService, OutlookService, CalDAVService)
  - `app/Models/`: Eloquent models (User, Display, Event, GoogleAccount, OutlookAccount)
  - `app/Data/`: Data transfer objects using Spatie Laravel Data

## Common Development Commands

### Flutter App
```bash
# Navigate to app directory
cd app

# Install dependencies
flutter pub get

# Run the app in development
flutter run

# Build for Android
flutter build apk

# Build for iOS
flutter build ios

# Run tests
flutter test

# Generate launcher icons
flutter pub run flutter_launcher_icons:main
```

### Laravel Backend
```bash
# Navigate to backend directory
cd backend

# Install PHP dependencies
composer install

# Install Node.js dependencies
npm install

# Run development server (with queue, logs, and vite)
composer dev

# Run individual services
php artisan serve                    # Web server
php artisan queue:listen --tries=1  # Queue worker
php artisan pail --timeout=0        # Log viewer
npm run dev                         # Vite asset bundler

# Database operations
php artisan migrate                  # Run migrations
php artisan db:seed                 # Seed database
php artisan migrate:fresh --seed    # Fresh migration with seeding

# Clear caches
php artisan config:clear
php artisan cache:clear
php artisan route:clear

# Run tests
php artisan test
./vendor/bin/pest

# Code formatting
./vendor/bin/pint
```

## Key Architecture Patterns

### Flutter App Patterns
- **GetX Controllers**: Handle state management and business logic
- **Service Layer**: Abstracts API calls and external dependencies
- **Repository Pattern**: Services act as repositories for data access
- **Translations**: Internationalization support with GetX translations

### Laravel Backend Patterns
- **API Resources**: Transform model data for API responses
- **Service Classes**: Encapsulate business logic and external API interactions
- **Data Classes**: Type-safe data transfer objects
- **Middleware**: Authentication and request processing
- **Webhooks**: Handle real-time calendar updates from external providers

## Environment Configuration

### Flutter App
- Uses `.env` file for environment variables
- Key variables: API endpoints, environment settings

### Laravel Backend
- Uses `.env` file for configuration
- Key variables: Database, cache, queue, calendar API credentials, webhook URLs

## Testing

### Flutter
- Widget tests in `/app/test/`
- Run with `flutter test`

### Laravel
- Feature and Unit tests in `/backend/tests/`
- Uses Pest PHP testing framework
- Run with `php artisan test` or `./vendor/bin/pest`

## External Integrations

### Calendar Providers
- **Google Calendar**: Uses Google Calendar API v3
- **Microsoft 365**: Uses Microsoft Graph API
- **CalDAV**: Generic CalDAV protocol support

### Licensing
- **LemonSqueezy**: Handles subscription billing for Pro features
- **License validation**: Cloud-based instance validation system

## Development Notes

- **PHP Requirements**: Backend requires PHP 8.4+ (composer.json specifies ^8.4)
- **Flutter Version**: Uses Flutter 3.29.0+ with Dart 3.7.0+
- **Database**: SQLite for local development, supports other databases for production
- **Queue System**: Laravel queues for background job processing
- **Real-time Updates**: Webhook handlers for calendar change notifications
- **Cross-platform**: Flutter app supports iOS and Android
- **Internationalization**: Support for English, Dutch, French, Spanish, and German

## Feature Documentation

`FEATURES.md` in the project root is a structured overview of all user-facing features. Keep it up to date whenever you add, change, or remove a feature. Update the relevant section immediately after implementing the change, don't leave it for later.

## UI Copy

**Never use an em dash (—) in user-facing text.** This covers Blade templates, Flutter strings and
translations, email and notification bodies, and any message the API returns to a client. Use a
comma, a colon, parentheses, or a full stop instead, whichever reads naturally. For a "no value"
placeholder in a table, use a plain hyphen (-).

This is about copy, not code: comments and documentation are unaffected.

## UI Consistency

**A new screen must look and behave like the screens it sits next to.** Consistency is what makes the
product feel considered rather than assembled; a page that is merely "correct" but styled its own way
reads as lower quality even when it works perfectly.

Before writing any markup, open the closest existing screen of the same kind and copy its structure:

- **Find the sibling first.** A new list page copies the users table on the admin dashboard
  (`resources/views/pages/admin.blade.php`). A new detail page copies the user detail page
  (`resources/views/pages/admin/user.blade.php`). Match the page chrome, the card, the table classes,
  the badge style, the empty state, the pagination and the date format exactly. Do not invent a
  second way to draw a table.
- **Same level, same chrome.** Anything reachable from a tab renders that tab bar and looks like the
  other tabs. A drill-down reached from a list gets the narrower container and a link back up, not
  the tab bar.
- **Extract, don't duplicate.** When a second screen needs the same chrome, pull it into a shared
  component (`resources/views/components/admin/tabs.blade.php`, `.../stats.blade.php`) rather than
  copying the markup. Copies drift.
- **Form fields carry the full class set.** This is Tailwind v4 **without** `@tailwindcss/forms`, so
  preflight strips the native control border and `border-gray-300` only sets a colour. Every
  `<input>`, `<select>` and `<textarea>` needs an explicit `border` plus padding, e.g.
  `block w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500`. A field
  without `border` renders invisible.
- **Reuse the existing components** in `resources/views/components/` (`x-cards.card`,
  `x-alerts.alert`, `x-icons.*`) instead of hand-rolling the same thing.

The same applies in the Flutter app: match the surrounding pages and reuse `app/lib/components/`.

## Security Considerations

- API authentication via Laravel Sanctum tokens
- Device-specific authentication and display assignment
- User activity tracking and session management
- Webhook signature validation for external calendar providers
- Environment-based configuration for sensitive data