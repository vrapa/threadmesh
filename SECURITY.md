# Security policy

ThreadMesh processes sensitive communication data and credentials. Do not report vulnerabilities in public issues.

## Supported versions

During alpha development, security fixes are applied only to the latest tagged release and the default branch.

| Version | Supported |
| --- | --- |
| Latest alpha | Yes |
| Default branch | Yes |
| Older versions | No |

## Reporting a vulnerability

Use **Security → Report a vulnerability** in the GitHub repository to submit a private vulnerability report. Include the affected version, impact, and reproducible steps using synthetic data. Never include real credentials, API tokens, private keys, mailbox contents, or a production database.

Please allow time to reproduce and assess the report before public disclosure. Security reports are handled privately until a fix and disclosure plan are available.

## Security boundaries

The MCP endpoint is designed for loopback or an authenticated private gateway. Normal IMAP synchronization is read-only. Publishing a local draft to IMAP requires explicit confirmation and does not send mail. ThreadMesh does not execute payments, open links, or trust instructions contained in email content.
