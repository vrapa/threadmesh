# Mail dashboard

ThreadMesh includes an optional read-only web UI implemented as a separate Nette application with Bootstrap 5. It does not add a framework dependency to the provider-neutral package core.

## Start locally

Start the ThreadMesh API and local Traefik first, then build and start the dashboard:

```bash
docker compose up -d
docker compose -f compose.dashboard.yaml up -d --build
```

Open `http://threadmesh.loc/dashboard/`. The loopback-only fallback is `http://127.0.0.1:8082/dashboard/`; set `THREADMESH_DASHBOARD_PORT` to change its host port.

The dashboard reads `THREADMESH_API_TOKEN` from the root `.env` file and sends it only from its server-side container to `http://api:8080`. The token is never embedded in HTML or browser requests.

Stop the UI without affecting the API, MCP server, or database:

```bash
docker compose -f compose.dashboard.yaml down
```

## Features

- color-coded critical, high, normal, low, and unassessed messages;
- filters for time range, assessment state, importance, and required action;
- subject, sender, recipients, timestamp, category, assessment summary, and recommended action;
- UTF-8 decoding of RFC 2047 subjects, address display names, and attachment names, including previously stored messages at read time;
- full message detail and attachment metadata;
- sandboxed HTML message preview with scripts, forms, navigation, and remote resources blocked.

The dashboard is intentionally read-only. It does not assess messages, create drafts, modify IMAP flags, send email, or expose stored credentials.

## Security boundary

Email headers and bodies are untrusted input. Latte escapes all list and metadata values. Original HTML is returned only by a dedicated preview endpoint and loaded into an iframe without sandbox permissions. The preview response applies a restrictive Content Security Policy, blocks referrers, disables MIME sniffing, and prevents external image requests that could act as tracking pixels.

The supplied deployment is for loopback-only local use. Do not publish the dashboard, API, MCP endpoint, or database browser to a LAN or the internet without an authenticated HTTPS gateway and an explicit threat review.

The dashboard is distributed under the same repository-level MIT license as ThreadMesh.
