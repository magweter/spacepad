# Setting up your self-hosted Spacepad

To self host this application, you can deploy your own instance using Docker and Traefik out of the box.
Using other reverse proxies will also work, but might require a bit more configuration.

Get started setting up your own self hosted (production) instance:

```bash
# Clone the repository
git clone https://github.com/magweter/spacepad.git
cd spacepad

# Create the environment config
cp .env.example .env
```

Set the app key for the application:

```bash
# Linux
sed -i "s/^APP_KEY=.*/APP_KEY=base64:$(openssl rand -base64 32)/" .env

# macOS
sed -i '' "s/^APP_KEY=.*/APP_KEY=base64:$(openssl rand -base64 32)/" .env

# Windows (PowerShell)
$appKey = "base64:" + [Convert]::ToBase64String((1..32 | ForEach-Object { Get-Random -Maximum 256 }))
(Get-Content .env) -replace '^APP_KEY=.*', "APP_KEY=$appKey" | Set-Content .env
```

Now open the .env file and configure your domain and email. Edit the DOMAIN and ACME_EMAIL variables:
```env
DOMAIN="mypublicdomain.com"
ACME_EMAIL="your-email@example.com"
```

> [!NOTE]
> When using Microsoft as integration, you are not able to use http due to security limitations. So your server is required to use https and be publicly available.

You can log into the app using three different methods; Email, Microsoft (OAuth) or Google (OAuth).

In order to use the regular email login you should configure an email provider, as it sends a 'magic link' by email. Edit the following variables:
```env
MAIL_MAILER=smtp
MAIL_HOST=
MAIL_PORT=587
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_FROM_ADDRESS="hello@example.com"
```

Configuring the following providers is optional, but you do require at least one. Leaving the client id of the provider empty will ensure it is not enabled.

Configuring the Outlook provider:
1. Go to [Azure Portal - App Registrations](https://entra.microsoft.com/#view/Microsoft_AAD_RegisteredApps/ApplicationsListBlade/quickStartType~/null/sourceType/Microsoft_AAD_IAM)
> [!NOTE]
> Please ensure you have selected 'multi-tenant' for your app. Using the single tenant configuration is not yet supported.
1. Click on 'New registration', add a name for the applicaton e.g. "Spacepad" and click 'register'
1. You will be taken to the Overview Page, record the "Application (client) ID" as this is the "AZURE_AD_CLIENT_ID="
1. Click on the 'Authentication' tab and create two new 'web' platforms:
    - https://your-domain.com/outlook-accounts/callback
    - https://your-domain.com/auth/microsoft/callback
1. Save, and click on 'API-permissions'
1. Click 'Microsoft Graph', click 'Delegated permissions' and search for and select the following permissions `Calendars.Read.Shared`, `Place.Read.All` and `User.Read`.
1. Save, and click on 'certificates and secrets'
1. Create a new secret (not certificate) and copy the value
1. Click on 'overview' and copy the 'client id'. Beware: this is the client ID value you need, not the ID of the secret you just created.
1. Paste the values in the .env 'AZURE_AD...' variables

Configuring the Google provider:
1. Go to [Google Cloud Console](https://console.cloud.google.com/)
1. Create a new project or select an existing one
1. Navigate to "APIs & Services" > "Credentials"
1. Click "Create Credentials" > "OAuth client ID"
1. Select "Web application" as the application type
1. Add authorized redirect URIs:
    - https://your-domain.com/google-accounts/callback
    - https://your-domain.com/auth/google/callback
1. Click "Create"
1. Enable the required Google APIs:
   - Go to "APIs & Services" > "Library"
   - Search for and enable:
     - Google Calendar API
     - Google Admin SDK API
1. Copy the Client ID and Client Secret
1. Paste the values in your .env file:
   - GOOGLE_CLIENT_ID=your_client_id
   - GOOGLE_CLIENT_SECRET=your_client_secret

Now you can choose to run the application with or without built-in proxy using Docker Compose.

To run the application with Traefik as a proxy:
```bash
docker compose -f docker-compose.prod.yml up -d
```

To run the application standalone (e.g. to use your own proxy):
```bash
docker compose up -d
```

Great! You should now be able to access the application at http://localhost or without proxy at http://localhost:8080.

Download the mobile app from the App Store or Play Store and follow the instructions 🚀

> **Email login security**
> 
> If you want to disable email login (for example, to prevent spam or abuse of the email login form), you can set the following environment variable in your `.env` file:
>
> ```env
> DISABLE_EMAIL_LOGIN=true
> ```
>
> When this is set to `true`, users will not be able to log in or register using email. Only OAuth (Microsoft/Google) will be available.

> **Restricting login to specific domains or emails**
>
> To restrict who can log in or register, set the `ALLOWED_LOGINS` environment variable in your `.env` file. This can be a comma-separated list of allowed email addresses and/or domains. For example:
>
> ```env
> ALLOWED_LOGINS=yourcompany.com,anothercompany.com,admin@special.com
> ```
>
> - To allow all users from a domain, add the domain (e.g. `yourcompany.com`).
> - To allow a specific email, add the full email address (e.g. `admin@special.com`).
> - Leave empty to allow all users.