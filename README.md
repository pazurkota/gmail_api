# Gmail SQLite Sync

A browser extension (Chrome and Firefox) that syncs your Gmail profile and the 10 most recent messages from your mailbox into a local SQLite database through a simple PHP backend.

When you click the extension icon (or when the browser starts):
1. The extension signs you in to Google and obtains an OAuth token (scope `gmail.readonly`).
2. It fetches your Gmail profile (address, message/thread counts) and metadata for the 10 latest messages (subject, sender, snippet, date) from the Gmail API.
3. It sends this data to the local PHP backend (`api.php`), which stores it in a SQLite file.

## Architecture

```
Chrome/Firefox (background.js)
        │  OAuth token (Google)
        ▼
  Gmail API (googleapis.com)
        │  profile + messages
        ▼
background.js ──POST + X-Gmail-Token──▶ api.php ──▶ Database/gmail.sqlite
```

- **`background.js`** — the extension's service worker (Manifest V3). Handles Chrome (`chrome.identity.getAuthToken`) and Firefox (a custom `launchWebAuthFlow` flow, since Firefox doesn't implement `identity.getAuthToken`) sign-in separately.
- **`manifest.json`** — extension declaration, permissions, and Chrome's OAuth configuration.
- **`api.php`** — HTTP endpoint that accepts data from the extension, protected by a shared secret (`X-Gmail-Token`).
- **`Api/`, `Service/`, `Repository/`, `Interface/`, `Database/`** — PHP backend logic (token validation, SQLite persistence, token rotation).
- **`reset_token.php`** — a separate endpoint for generating a new `GMAIL_API_TOKEN` (requires the current token).

## Requirements

- PHP 8.1+ with the `pdo_sqlite` extension
- [Composer](https://getcomposer.org/)
- Chrome/Chromium **or** Firefox 115+
- A Google account + project in the [Google Cloud Console](https://console.cloud.google.com/)

## 1. Install backend dependencies

```bash
composer install
```

## 2. Configure `.env`

```bash
cp env.example .env
```

Fill in the `.env` file:

```
GMAIL_API_TOKEN=          # generate e.g. with: php -r "echo bin2hex(random_bytes(32));"
# GMAIL_DB_PATH=/absolute/path/to/gmail.sqlite   # optional, defaults to Database/gmail.sqlite
GOOGLE_FIREFOX_CLIENT_ID=  # see the "Firefox" section below
```

`GMAIL_API_TOKEN` is a secret shared between the extension and `api.php` — the extension sends it in the `X-Gmail-Token` header, and the backend validates it. `.env` is in `.gitignore` and must never be committed.

## 3. Google Cloud (OAuth) setup

Chrome and Firefox use **two different OAuth client types** — you can't use a single one for both.

### a) Enable the Gmail API

Google Cloud Console → **APIs & Services → Library** → search for **Gmail API** → **Enable**.
(Without this step, sign-in will succeed, but requests to the Gmail API will return a 403 error.)

### b) OAuth consent screen

Google Cloud Console → **APIs & Services → OAuth consent screen**:
- User type: **External**; status **Testing** is enough for personal use.
- Add the `.../auth/gmail.readonly` scope.
- Under **Test users**, add the Gmail address you'll sign in with in the extension — without this, Google will return `Error 403: access_denied`.

### c) OAuth client for Chrome

**APIs & Services → Credentials → Create Credentials → OAuth client ID**:
- Type: **Chrome Extension**
- Application ID: the extension ID shown in `chrome://extensions` (you'll see it once you load the extension in step 5)
- Copy the generated `client_id` and put it into `manifest.json`:

```json
"oauth2": {
  "client_id": "YOUR_ID.apps.googleusercontent.com",
  ...
}
```

### d) OAuth client for Firefox

Firefox doesn't support `chrome.identity.getAuthToken`, so the extension drives its own OAuth flow (`launchWebAuthFlow`) and needs a different client type:

1. Load the extension in Firefox (step 5), open `about:debugging#/runtime/this-firefox` → **Inspect** next to "Gmail SQLite Sync" → the Console tab.
2. Click the extension icon — the console will log `[Gmail Sync] Firefox redirect URI: https://....extensions.allizom.org/`. Copy that URL.
3. **APIs & Services → Credentials → Create Credentials → OAuth client ID**:
   - Type: **Web application**
   - Authorized redirect URIs: paste the copied URL
4. Put the resulting `client_id` into `.env` as `GOOGLE_FIREFOX_CLIENT_ID`.

> This redirect URI depends on `browser_specific_settings.gecko.id` in `manifest.json` — if you change it, the URI changes too, and you'll need to update the redirect URI in the Google Cloud Console.

## 4. Run the backend

```bash
php -S localhost:8000
```

The backend listens at `http://localhost:8000/api.php`. If you want to change the address, also update `CONFIG.apiUrl` in `background.js` and `host_permissions` in `manifest.json`.

## 5. Load the extension in your browser

### Chrome / Chromium
1. `chrome://extensions`
2. Enable **Developer mode** (top right)
3. **Load unpacked** → select the project directory
4. Copy the assigned extension ID — needed in step 3c

### Firefox
1. `about:debugging#/runtime/this-firefox`
2. **Load Temporary Add-on…** → select the `manifest.json` file

The extension is removed when Firefox closes (temporary install) — you'll need to reload it every time you restart the browser during development.

## 6. Test it

1. Click the extension icon in the toolbar.
2. Sign in with the Google account you added as a tester.
3. Check that `Database/gmail.sqlite` was created, with `users` and `messages` tables.

Manual backend test without a browser:

```bash
curl -X POST http://localhost:8000/api.php \
  -H "Content-Type: application/json" \
  -H "X-Gmail-Token: <value from .env>" \
  -d '{"user":{"email":"test@example.com","messages_total":1,"threads_total":1}}'
```

## Token rotation

`GMAIL_API_TOKEN` can be regenerated without manually editing `.env`:

```bash
curl -X POST http://localhost:8000/reset_token.php \
  -H "X-Gmail-Token: <current token>"
```

This returns the new token and saves it to `.env`. Remember to also update `GMAIL_API_TOKEN` that the extension loads from its own copy of `.env` (see below).

## How the extension reads `.env`

`background.js` loads `.env` from the extension's own directory via `runtime.getURL('.env')` — so the `.env` file must physically exist in the project directory you point to with "Load unpacked"/"Load Temporary Add-on". Reload the extension in the browser after every change to `.env`.

## Troubleshooting

| Symptom | Cause | Fix |
|---|---|---|
| `Error 401: invalid_client` | `client_id` in `manifest.json`/`.env` is still a placeholder or has a typo | Fill in the real `client_id` (steps 3c/3d) |
| `Error 403: access_denied` (allizom.org / testers) | Your Google account isn't added as a tester on the OAuth consent screen | Add your email under **Test users** |
| `Gmail sync failed: Error: Gmail API /profile -> 403` | The Gmail API isn't enabled in the GCP project | Enable **Gmail API** under **APIs & Services → Library** |
| Sign-in works, but nothing shows up in SQLite | Backend isn't running / wrong port / CORS | Check that `php -S localhost:8000` is running, and check the extension's background console for errors |
| `Class "App\Api\GmailApi" not found` | Autoloader hasn't been generated | `composer dump-autoload` |

## Security notes

- `.env` (with `GMAIL_API_TOKEN` and `GOOGLE_FIREFOX_CLIENT_ID`) is loaded directly from the extension's directory — keep it local only, and never publish a packaged extension with a real `.env` inside it.
- `Database/*.sqlite` contains real content from your mailbox (subjects, senders, snippets) and is in `.gitignore` — don't commit it manually.
- The backend only accepts CORS requests from `moz-extension://` and `chrome-extension://` origins — other sites can't call `api.php` from a browser.
