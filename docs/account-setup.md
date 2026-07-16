# IMAP account setup on Windows

Use the interactive PowerShell helper to add an IMAP account without putting its password in shell history, command-line arguments, a JSON file, or chat. The helper calls the local bearer-protected ThreadMesh API; the API encrypts the password before storing it in SQLite.

## Run the helper

Start the ThreadMesh API and make sure `THREADMESH_API_TOKEN` is set. For Docker, keep the token in the repository `.env` file and start the stack as described in the [Docker guide](docker.md).

From the repository root, run:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File .\scripts\configure-imap-account.ps1
```

The helper defaults to `http://127.0.0.1:8080` and reads the token from the current environment or the repository `.env`. Override either location when needed:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File .\scripts\configure-imap-account.ps1 `
  -ApiUrl http://127.0.0.1:18080 `
  -EnvFile C:\path\to\threadmesh.env
```

## What it does

1. Prompts for the account ID, display name, IMAP server, port, encryption, certificate validation, and username.
2. Reads the IMAP password or app password with hidden input.
3. Stores the account through the local API and tests the IMAP connection.
4. Discovers folders and lets you select the exact server folder used for reply drafts.
5. Lets you select one or more folders for incremental synchronization.
6. Initializes the selected folders only after you type `INITIALIZE`.

Initialization starts at each selected folder's current highest UID. Existing messages are not imported; only messages arriving after initialization are synchronized. Selecting only `INBOX` is the safest default. Do not select Sent, Trash, Junk, Spam, or archive folders unless you intentionally want ThreadMesh to process them.

Re-run the helper with the same account ID to update a password or connection settings. A failed connection test leaves the encrypted account record in place so it can be corrected by running the helper again.

## Security notes

- Run the helper on the same trusted machine as ThreadMesh.
- Keep the API bound to loopback or behind an authenticated HTTPS gateway.
- Prefer an app password when the mail provider supports one.
- Never put the password in a command argument, checked-in file, copied API request, or support message.
- Protect `.env`, the SQLite database, and `THREADMESH_MASTER_KEY`; the database cannot decrypt account passwords without the master key.

For programmatic account management, see the [HTTP API](api.md).
