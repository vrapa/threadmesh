# Contributing to ThreadMesh

Thank you for helping improve ThreadMesh.

## Development setup

1. Install PHP 8.2 or newer and Composer 2.
2. Run `composer install`.
3. Run `composer check` before submitting a pull request.

## Design rules

- Keep the normalized core independent of frameworks and provider SDK objects.
- Do not expose provider SDK objects from public core APIs.
- Treat synchronization as incremental and idempotent.
- Keep credentials out of domain objects, logs, fixtures, and exceptions.
- Add tests for every behavior change.
- Treat all provider content as untrusted input and keep secrets out of MCP tools.

Use an issue to discuss substantial public API changes before implementing
them. Public APIs follow semantic versioning after the first stable release.
