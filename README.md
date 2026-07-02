# TB Account Sender

A Thunderbird extension (Manifest V3) that synchronizes mail accounts and the most recent messages with a local PHP backend, which persists the data to a SQLite database.

## Architecture

```
Thunderbird (background.js)
        │  POST /api.php  (JSON, X-Thunderbird-Token header)
        ▼
api.php ──▶ Api/ThunderbirdApi.php
        │        │
        │        ├─▶ Service/AccountSyncService.php ──▶ Repository/SqliteAccountRepository.php
        │        └─▶ Service/MessageSyncService.php  ──▶ Repository/SqliteMessageRepository.php
        │
        ▼
Database/thunderbird.sqlite  (tables: accounts, messages)
```

The backend is written in PHP and split into layers:

- **Interface/** — repository contracts (`AccountRepositoryInterface`, `MessageRepositoryInterface`)
- **Repository/** — SQLite (PDO) implementations for accounts and messages
- **Service/** — API token validation and save orchestration (`AccountSyncService`, `MessageSyncService`, `TokenResetService`, `EnvService`)
- **Api/** — HTTP entry points (`ThunderbirdApi` routes by payload shape, `ResetTokenApi` rotates the token; both handle CORS)
- **Database/** — SQLite connection and schema migration (`Database.php`)

## Features

- Syncs mail accounts (`browser.accounts.list`) on Thunderbird startup, when a new account is added, and manually (toolbar icon click).
- Syncs the 10 most recent messages across all accounts/folders when new mail arrives (`browser.messages.onNewMailReceived`) and during a full sync.
- Idempotent writes (upsert by `tb_account_id` / message `id`) — resending the same data does not create duplicates.
- The message repository keeps only the 10 most recent records (older ones are pruned).

## Requirements

- Thunderbird 128+ (Manifest V3, tested on 140.12.0esr)
- PHP 8.1+ with the `pdo_sqlite` extension
- [Composer](https://getcomposer.org/)

## Running the backend

```bash
composer install
cp .env.example .env
# edit .env and set THUNDERBIRD_API_TOKEN to a random secret
php -S localhost:8000
```

The server listens for `POST /api.php` requests. The `Database/thunderbird.sqlite` database is created automatically on the first request.

## Installing the extension in Thunderbird

1. `Tools → Developer Tools → Debug Add-ons`
2. "Load Temporary Add-on" → select the `manifest.json` file
3. Accept the requested permissions (`accountsRead`, `messagesRead`)
4. Add the extension icon to the toolbar to trigger a manual sync

## Configuration

The API token must match on both sides of the integration:

- **Backend** (`api.php`) — loaded from a `.env` file via [`vlucas/phpdotenv`](https://github.com/vlucas/phpdotenv). Copy `.env.example` to `.env` and set:
  ```
  THUNDERBIRD_API_TOKEN=<a long random secret>
  # THUNDERBIRD_DB_PATH=/absolute/path/to/thunderbird.sqlite   # optional override
  ```
  Generate a strong token with, e.g.: `php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"`.
  `.env` is gitignored and must never be committed. If `THUNDERBIRD_API_TOKEN` is not set, `api.php` responds with a `500` error instead of falling back to a hardcoded value.
- **Extension** (`background.js`) → `CONFIG.apiUrl`. The token is no longer hard-coded: `background.js` fetches the extension's own `.env` file at runtime (via `browser.runtime.getURL('.env')`) and reads `THUNDERBIRD_API_TOKEN` from it, so the same `.env` used by the backend also configures the extension. If `.env` is missing or the key is unset, synchronization fails with a clear error instead of falling back to a hardcoded value.

### Resetting the token

`POST /reset_token.php` rotates `THUNDERBIRD_API_TOKEN` and writes the new value back to `.env`. It requires the **current** token in the `X-Thunderbird-Token` header, so only a caller who already holds a valid token can trigger a rotation:

```bash
curl -X POST http://localhost:8000/reset_token.php \
  -H "X-Thunderbird-Token: <current token>"
```

Response:
```json
{
  "status": "success",
  "message": "Auth token has been reset.",
  "new_token": "<new token>",
  "server_time": "2026-07-02 14:00:00"
}
```

After a reset, the extension's in-memory token cache is stale until it is reloaded (Thunderbird restart, or "Reload" in Debug Add-ons), since `background.js` only re-reads `.env` once per session.

## API contract

`POST /api.php`

Headers:
```
Content-Type: application/json
X-Thunderbird-Token: <token>
```

Accounts payload:
```json
{
  "accounts": [
    {
      "tb_account_id": "account1",
      "account_name": "Test",
      "type": "imap",
      "identities": [{ "name": "...", "email": "...", "organization": "..." }]
    }
  ]
}
```

Messages payload:
```json
{
  "messages": [
    {
      "id": "account1_123",
      "subject": "...",
      "author": "...",
      "date": "2026-07-01 08:00:00",
      "body_preview": ""
    }
  ]
}
```

Response (success): `{"status":"success", ...}` with a `200` status code.
Errors: `401` (invalid token), `400` (invalid payload), `405` (wrong HTTP method).

---

`POST /reset_token.php`

Headers:
```
X-Thunderbird-Token: <current token>
```

Response (success): `{"status":"success", "new_token": "...", ...}` with a `200` status code.
Errors: `401` (invalid/missing current token), `405` (wrong HTTP method), `500` (server misconfigured, `THUNDERBIRD_API_TOKEN` not set).

## Database schema

```sql
accounts (tb_account_id TEXT PRIMARY KEY, account_name TEXT, identities TEXT, last_sync TEXT, status TEXT)
messages (id TEXT PRIMARY KEY, subject TEXT, author TEXT, date TEXT, body_preview TEXT, save_at TEXT)
```

## License

MIT licensed — see [LICENSE.md](LICENSE.md).
