# HTTP API

ThreadMesh exposes a small JSON API for account configuration and application integrations. Run it locally with:

```bash
php -S 127.0.0.1:8080 -t public public/router.php
```

Set `THREADMESH_MASTER_KEY`, `THREADMESH_API_TOKEN`, and optionally `THREADMESH_DB` first. Every `/v1` request requires:

```http
Authorization: Bearer <THREADMESH_API_TOKEN>
Content-Type: application/json
```

`GET /health` is public. Keep the API on loopback during local use. A remote deployment requires HTTPS, authentication at the edge, restricted network access, and protected environment variables.

## Endpoints

| Method | Path | Purpose |
| --- | --- | --- |
| `GET` | `/health` | Process health check |
| `GET` | `/v1/accounts` | List configured accounts without secrets |
| `POST` | `/v1/accounts` | Create or update an IMAP account |
| `POST` | `/v1/accounts/{id}/test` | Test IMAP authentication and connectivity |
| `GET` | `/v1/accounts/{id}/folders` | Discover selectable IMAP folders |
| `POST` | `/v1/accounts/{id}/initialize` | Initialize selected folders at their current highest UID |
| `POST` | `/v1/accounts/{id}/backfill` | Explicitly import one page of historical mail |
| `POST` | `/v1/sync` | Synchronize one bounded batch from enabled folders |
| `GET` | `/v1/mailbox` | List email overview records within a time range |
| `GET` | `/v1/emails?limit=20` | List emails without a stored assessment |
| `GET` | `/v1/emails/{id}` | Read one stored email and assessment |
| `POST` | `/v1/emails/{id}/assessment` | Store or replace a structured assessment |
| `POST` | `/v1/emails/{id}/drafts` | Create a new local reply draft |
| `GET` | `/v1/alerts?limit=50` | List important, actionable, or invoice assessments |
| `POST` | `/v1/drafts/{id}/publish` | Append a confirmed local draft to IMAP Drafts |

## Configure an account

```json
{
  "id": "work",
  "displayName": "Work email",
  "secret": "app-password",
  "enabled": true,
  "configuration": {
    "host": "imap.example.com",
    "port": 993,
    "encryption": "ssl",
    "validateCertificate": true,
    "username": "me@example.com",
    "draftFolder": "Drafts"
  }
}
```

Supported encryption values are `ssl`, `tls`, and `starttls`. Omitting `secret` during an update preserves the encrypted existing secret. Obtain the exact `draftFolder` path from the folder discovery endpoint.

## Initialize and synchronize

Initialization deliberately imports no history:

```json
{
  "streams": ["INBOX"]
}
```

Synchronize all initialized accounts with an empty JSON object, or select one account and batch size:

```json
{
  "accountId": "work",
  "batchSize": 100
}
```

If `hasMore` is true, repeat synchronization until it becomes false. Cursor advancement and item persistence are transactional.

## Import recent history

Normal initialization deliberately imports no history and remains unchanged. Use the explicit backfill operation when a new installation needs recent messages:

```json
{
  "streamId": "INBOX",
  "days": 7,
  "batchSize": 100
}
```

`days` must be between 1 and 31. An exact ISO 8601 `since` timestamp may be used instead. The response contains the normalized `since`, an opaque `cursor`, and `hasMore`. When `hasMore` is true, repeat the request with the same `since` and the returned cursor:

```json
{
  "streamId": "INBOX",
  "since": "2026-07-24T08:00:00+00:00",
  "batchSize": 100,
  "cursor": "<cursor returned by the previous response>"
}
```

Backfill reads with PEEK, does not change message flags, and never moves the normal synchronization cursor. Each page is persisted transactionally and stable source references make repeated pages idempotent. If UIDVALIDITY changes, restart the historical import without its cursor.

## List the mailbox overview

`GET /v1/mailbox` returns compact overview records rather than full message bodies. It defaults to the last seven days and includes both assessed and unassessed mail.

Supported query parameters are:

- `since` and `until` as ISO 8601 timestamps;
- `accountId`;
- comma-separated `importance` values (`low`, `normal`, `high`, `critical`);
- `assessed=true|false`;
- `requiresAction=true|false`;
- `limit`, capped at 200.

For example:

```http
GET /v1/mailbox?since=2026-07-24T00:00:00Z&importance=high,critical&limit=100
```

The overview contains sender metadata, subject, source timestamp, assessment status, importance, category, summary, and recommended action. Fetch `GET /v1/emails/{id}` only when the full stored body is needed.

## Store an assessment

```json
{
  "importance": "high",
  "category": "invoice",
  "summary": "Hosting invoice due next week.",
  "requiresAction": true,
  "dueAt": "2026-08-01",
  "amount": 1250.50,
  "currency": "CZK",
  "recommendedAction": "Review and pay after approval.",
  "reason": "The sender and attached invoice metadata appear consistent."
}
```

Importance must be `low`, `normal`, `high`, or `critical`. Classification is an agent conclusion, not a guarantee that an invoice or sender is legitimate.

## Drafts

Create a local draft first:

```json
{
  "subject": "Re: Hosting invoice",
  "bodyText": "Thank you. I will review the invoice."
}
```

Publishing to IMAP is a separate write operation:

```json
{
  "confirmed": true
}
```

ThreadMesh appends the draft with the `\\Draft` flag. It has no SMTP transport and cannot send the message.
