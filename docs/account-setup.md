# IMAP account setup on Windows

Use the interactive PowerShell helper to add an IMAP account without putting its password in shell history, command-line arguments, a JSON file, or chat. The helper calls the local bearer-protected ThreadMesh API; the API encrypts the password before storing it in SQLite.

## Run the helper

Start the ThreadMesh API and make sure `THREADMESH_API_TOKEN` is set. For Docker, keep the token in the repository `.env` file and start the stack as described in the [Docker guide](docker.md).

From the repository root, run:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File .\scripts\configure-imap-account.ps1
```

For Gmail, use the preset that fixes the connection to `imap.gmail.com:993` with SSL and initializes only `INBOX`:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File .\scripts\configure-imap-account.ps1 -Gmail
```

The Gmail preset asks for the full Gmail address and a Google app password with hidden input. Do not enter the main Google Account password. Creating an app password requires 2-Step Verification and may be unavailable when a Google Workspace administrator disallows it. ThreadMesh does not support Google OAuth yet.

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

With `-Gmail`, the server, port, encryption, and certificate validation are fixed to the Gmail settings, and step 5 is skipped because only `INBOX` is selected. Folder discovery and the explicit initialization confirmation are still performed. The reply-draft folder remains an explicit choice because its Gmail IMAP path can be localized.

Initialization starts at each selected folder's current highest UID. Existing messages are not imported; only messages arriving after initialization are synchronized. Selecting only `INBOX` is the safest default. Do not select Sent, Trash, Junk, Spam, or archive folders unless you intentionally want ThreadMesh to process them.

Re-run the helper with the same account ID to update a password or connection settings. A failed connection test leaves the encrypted account record in place so it can be corrected by running the helper again.

## Security notes

- Run the helper on the same trusted machine as ThreadMesh.
- Keep the API bound to loopback or behind an authenticated HTTPS gateway.
- Prefer an app password when the mail provider supports one.
- Never put the password in a command argument, checked-in file, copied API request, or support message.
- Protect `.env`, the SQLite database, and `THREADMESH_MASTER_KEY`; the database cannot decrypt account passwords without the master key.

For programmatic account management, see the [HTTP API](api.md).
