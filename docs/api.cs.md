# HTTP API

ThreadMesh poskytuje malé JSON API pro konfiguraci účtů a lokální aplikace. Spusťte je pomocí:

```bash
php -S 127.0.0.1:8080 -t public public/router.php
```

Nastavte `THREADMESH_MASTER_KEY`, `THREADMESH_API_TOKEN` a případně `THREADMESH_DB`. Každý požadavek pod `/v1` vyžaduje:

```http
Authorization: Bearer <THREADMESH_API_TOKEN>
Content-Type: application/json
```

`GET /health` je veřejný. API ponechte při lokálním použití na loopback rozhraní. Vzdálené nasazení vyžaduje HTTPS, autentizaci na hraně sítě, omezený síťový přístup a chráněné proměnné prostředí.

## Endpointy

| Metoda | Cesta | Účel |
| --- | --- | --- |
| `GET` | `/health` | Kontrola běhu procesu |
| `GET` | `/v1/accounts` | Výpis účtů bez přístupových údajů |
| `POST` | `/v1/accounts` | Vytvoření nebo úprava IMAP účtu |
| `POST` | `/v1/accounts/{id}/test` | Ověření IMAP připojení |
| `GET` | `/v1/accounts/{id}/folders` | Seznam dostupných IMAP složek |
| `POST` | `/v1/accounts/{id}/initialize` | Inicializace složek na aktuálním nejvyšším UID |
| `POST` | `/v1/accounts/{id}/backfill` | Explicitní stránkovaný import historie |
| `POST` | `/v1/sync` | Synchronizace jedné omezené dávky |
| `GET` | `/v1/mailbox` | Časově filtrovaný přehled e-mailů a hodnocení |
| `GET` | `/v1/emails?limit=20` | E-maily bez uloženého hodnocení |
| `GET` | `/v1/emails/{id}` | Detail e-mailu a hodnocení |
| `POST` | `/v1/emails/{id}/assessment` | Uložení nebo nahrazení hodnocení |
| `POST` | `/v1/emails/{id}/drafts` | Vytvoření lokálního konceptu odpovědi |
| `GET` | `/v1/alerts?limit=50` | Důležitá, akční a fakturační upozornění |
| `POST` | `/v1/drafts/{id}/publish` | Uložení potvrzeného konceptu do IMAP Drafts |

## Přehled schránky

`GET /v1/mailbox` vrací stručná metadata pro seznam, nikoli celé tělo zprávy. Bez parametru `since` zobrazí posledních sedm dní. Výsledek obsahuje odesílatele, předmět, čas, stav hodnocení, důležitost, kategorii, shrnutí a doporučenou akci.

Podporované parametry:

- `since` a `until` jako ISO 8601 timestamp;
- `accountId`;
- `importance=low,normal,high,critical`;
- `assessed=true|false`;
- `requiresAction=true|false`;
- `limit` v rozsahu 1 až 200.

Příklad:

```http
GET /v1/mailbox?since=2026-07-24T00:00:00Z&importance=high,critical&limit=100
```

Pro načtení celého těla použijte následně `GET /v1/emails/{id}`.

## Inicializace a historický import

Běžná inicializace záměrně neimportuje historii. Uloží aktuální nejvyšší UID a další synchronizace čte pouze novější zprávy.

Starší zprávy lze načíst explicitně:

```json
{
  "streamId": "INBOX",
  "days": 7,
  "batchSize": 100
}
```

Místo `days` lze poslat přesný ISO 8601 parametr `since`. Odpověď obsahuje `items`, `hasMore`, `cursor` a normalizované `since`. Pokud je `hasMore=true`, zopakujte požadavek se stejným `since` a vráceným `cursor`:

```json
{
  "streamId": "INBOX",
  "since": "2026-07-24T08:00:00+00:00",
  "batchSize": 100,
  "cursor": "<kurzor vrácený předchozí odpovědí>"
}
```

Backfill používá IMAP PEEK, nemění příznaky zpráv a neposouvá kurzor běžné synchronizace. Ukládání je idempotentní. Při změně UIDVALIDITY se import zastaví a musí se restartovat bez historického kurzoru.

## Hodnocení a koncepty

Důležitost musí být `low`, `normal`, `high` nebo `critical`. Hodnocení je závěr agenta, nikoli důkaz legitimity odesílatele, faktury nebo přílohy.

Koncept se nejprve ukládá pouze lokálně. Publikování do IMAP vyžaduje samostatný požadavek s `confirmed=true`, přidá příznak `\Draft` a zprávu nikdy neodešle.
