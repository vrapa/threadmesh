# Local Docker deployment

Docker Compose provides the simplest consistent local runtime on Windows, Linux, and macOS. It starts two containers from one image:

- `api` exposes the ThreadMesh HTTP API at `http://127.0.0.1:8080`;
- `mcp` exposes MCP at `http://127.0.0.1:8081/mcp`;
- both share the `threadmesh-data` named volume containing SQLite.

This configuration is intended for local use. The host ports bind only to `127.0.0.1` and must not be changed to a public interface without an authenticated HTTPS gateway.

## Requirements

- Docker Desktop, Docker Engine with Compose, or a compatible implementation
- Free host ports 8080 and 8081, unless overridden

## First start

Copy the example environment file:

```bash
cp .env.example .env
```

On PowerShell use:

```powershell
Copy-Item .env.example .env
```

Build the image, then generate secrets without requiring host PHP:

```bash
docker compose build
docker compose run --rm api php bin/threadmesh key:generate
docker compose run --rm api php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
```

Put the first result into `THREADMESH_MASTER_KEY` and the second into `THREADMESH_API_TOKEN` in `.env`. Never commit `.env`.

Start both services:

```bash
docker compose up -d
docker compose ps
```

The API container must become `healthy`. Inspect logs if it does not:

```bash
docker compose logs api
docker compose logs mcp
```

Verify the API:

```bash
curl http://127.0.0.1:8080/health
```

Continue with [HTTP API account configuration](api.md), then add `http://127.0.0.1:8081/mcp` to the client as described in the [MCP guide](mcp.md).

## Ports

Override host ports in `.env` when defaults are occupied:

```text
THREADMESH_API_PORT=18080
THREADMESH_MCP_PORT=18081
```

Only the host-side port changes. The container ports remain 8080 and 8081.

## Security model

The image runs as the unprivileged `threadmesh` user. Compose removes Linux capabilities, enables `no-new-privileges`, mounts the root filesystem read-only, and gives write access only to `/tmp` and the SQLite volume.

The MCP process listens on `0.0.0.0` inside its isolated container so Docker can publish it. Compose publishes it exclusively on host loopback. Do not change the port mapping from `127.0.0.1:...` to `0.0.0.0:...`.

The built-in PHP server is suitable for local use, not for direct public production hosting.

## Stop, update, and delete

Stop containers while retaining SQLite data:

```bash
docker compose stop
```

Start them again:

```bash
docker compose start
```

Rebuild after updating the source:

```bash
docker compose build --pull
docker compose up -d
```

`docker compose down` removes containers and the network but retains the named volume. **Do not run `docker compose down --volumes` unless you intentionally want to delete the ThreadMesh database.**

## Backup

Stop writes, copy the SQLite file from the container, and restart:

```bash
docker compose stop
docker compose cp api:/app/var/threadmesh.sqlite ./threadmesh.sqlite.backup
docker compose start
```

The backup contains private email data and encrypted credentials. Store it securely and keep a separate protected backup of `THREADMESH_MASTER_KEY`; the database cannot decrypt credentials without that key.
