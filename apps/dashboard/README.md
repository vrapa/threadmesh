# ThreadMesh Dashboard

Read-only Nette and Bootstrap UI for the ThreadMesh mailbox API. It is distributed under the repository's MIT license.

The dashboard keeps `THREADMESH_API_TOKEN` on the server and never exposes it to the browser. Email HTML is rendered in a sandboxed iframe with scripts, forms, navigation, and remote resources blocked.

Run it through the root Docker Compose configuration and open `http://threadmesh.loc/dashboard/`. For standalone development, install dependencies with `composer install`, set `THREADMESH_API_URL` and `THREADMESH_API_TOKEN`, and run:

```bash
php -S 127.0.0.1:8082 -t public public/router.php
```
