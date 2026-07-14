# ThreadMesh

ThreadMesh is a framework-agnostic PHP toolkit for connecting messages, tasks,
and developer workflows. It provides a small shared domain model and connector
contracts that integrations such as IMAP, Jira, and Bitbucket can implement.

> The project is in early development. The first production connector will be
> read-only IMAP synchronization.

## Why ThreadMesh?

Applications should not need a different internal workflow for every external
service. ThreadMesh normalizes external records into items and keeps
provider-specific operations behind connector contracts.

```text
IMAP ───────┐
Jira ───────┼── Connector ── Item ── your application
Bitbucket ──┘                    └── actions and rules
```

## Requirements

- PHP 8.2 or newer
- Composer 2

## Installation

The package will be available after its first tagged release:

```bash
composer require threadmesh/core
```

For development, add the repository as a local Composer path repository.

## Core concepts

- `Connector` synchronizes a provider and executes provider-specific actions.
- `Item` is the normalized representation of a message, task, comment, pull
  request, or other external record.
- `SourceReference` links an item to its immutable provider identity.
- `SyncCursor` stores opaque incremental synchronization state.
- `SyncResult` returns normalized items and the next cursor together.

## Example

```php
use ThreadMesh\Contract\Connector;
use ThreadMesh\Domain\Account;

function synchronize(Connector $connector, Account $account): void
{
    $result = $connector->synchronize($account);

    foreach ($result->items as $item) {
        echo $item->title . PHP_EOL;
    }

    // Persist $result->nextCursor for the next incremental synchronization.
}
```

Credentials are intentionally not part of `Account`. Each connector owns its
credential storage and resolves credentials from the account identifier.

## Roadmap

- `0.1`: stable core contracts and in-memory test utilities
- `0.2`: read-only IMAP connector with incremental synchronization
- `0.3`: message actions and rule evaluation
- later: Symfony integration, Jira, and Bitbucket connectors

See [the architecture notes](docs/architecture.md) for the design boundaries.

## Development

```bash
composer install
composer check
```

## Contributing

Contributions are welcome. Read [CONTRIBUTING.md](CONTRIBUTING.md) before
opening an issue or pull request.

## License

ThreadMesh is released under the [MIT License](LICENSE).
