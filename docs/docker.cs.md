# Lokální spuštění v Dockeru

Docker Compose je nejjednodušší jednotný způsob lokálního spuštění na Windows, Linuxu a macOS. Z jednoho image spustí dva kontejnery:

- `api` zpřístupní HTTP API na `http://127.0.0.1:8080`;
- `mcp` zpřístupní MCP na `http://127.0.0.1:8081/mcp`;
- oba používají společný named volume `threadmesh-data` se SQLite databází.

Konfigurace je určena pro lokální provoz. Porty jsou na hostiteli navázané pouze na `127.0.0.1`. Bez autentizované HTTPS brány je nikdy nevystavujte do internetu.

## Požadavky

- Docker Desktop nebo Docker Engine s Compose
- volné hostitelské porty 8080 a 8081, případně jejich změna

## První spuštění

Zkopírujte vzorové proměnné prostředí:

```bash
cp .env.example .env
```

V PowerShellu:

```powershell
Copy-Item .env.example .env
```

Sestavte image a vygenerujte tajné hodnoty bez potřeby lokálního PHP:

```bash
docker compose build
docker compose run --rm api php bin/threadmesh key:generate
docker compose run --rm api php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
```

První výsledek vložte v `.env` do `THREADMESH_MASTER_KEY`, druhý do `THREADMESH_API_TOKEN`. Soubor `.env` nikdy necommitujte.

Spusťte obě služby:

```bash
docker compose up -d
docker compose ps
```

API kontejner musí přejít do stavu `healthy`. Případné chyby zobrazíte:

```bash
docker compose logs api
docker compose logs mcp
```

Ověřte API:

```bash
curl http://127.0.0.1:8080/health
```

Ve Windows přidejte první schránku pomocí [interaktivního průvodce IMAP účtem](account-setup.cs.md). Pro programové nastavení použijte [HTTP API](api.md). Poté připojte `http://127.0.0.1:8081/mcp` podle [MCP návodu](mcp.md).

## Změna portů

Pokud jsou výchozí porty obsazené, nastavte v `.env`:

```text
THREADMESH_API_PORT=18080
THREADMESH_MCP_PORT=18081
```

Mění se pouze porty hostitele. Uvnitř kontejnerů zůstávají 8080 a 8081.

## Zabezpečení

Image běží pod neprivilegovaným uživatelem `threadmesh`. Compose odebere Linux capabilities, nastaví `no-new-privileges`, připojí root filesystem pouze pro čtení a povolí zápis jen do `/tmp` a SQLite volume.

MCP uvnitř izolovaného kontejneru poslouchá na `0.0.0.0`, aby jej Docker mohl publikovat. Compose jej ale na hostiteli zpřístupní výhradně přes loopback. Neměňte mapování portu z `127.0.0.1:...` na `0.0.0.0:...`.

Vestavěný PHP server je vhodný pro lokální použití, nikoli pro přímý veřejný produkční provoz.

## Zastavení, aktualizace a odstranění

Kontejnery zastavíte bez odstranění dat:

```bash
docker compose stop
```

Znovu je spustíte:

```bash
docker compose start
```

Po aktualizaci zdrojového kódu sestavte nový image:

```bash
docker compose build --pull
docker compose up -d
```

`docker compose down` odstraní kontejnery a síť, ale zachová named volume. **Příkaz `docker compose down --volumes` nepoužívejte, pokud záměrně nechcete smazat ThreadMesh databázi.**

## Záloha

Nejprve zastavte zápisy, zkopírujte SQLite soubor a služby znovu spusťte:

```bash
docker compose stop
docker compose cp api:/app/var/threadmesh.sqlite ./threadmesh.sqlite.backup
docker compose start
```

Záloha obsahuje soukromá data e-mailů a šifrované přihlašovací údaje. Chraňte ji a samostatně zálohujte také `THREADMESH_MASTER_KEY`; bez tohoto klíče nelze údaje v databázi dešifrovat.
