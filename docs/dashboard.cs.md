# Mailový dashboard

ThreadMesh obsahuje volitelné webové UI pouze pro čtení, vytvořené jako samostatná Nette aplikace s Bootstrapem 5. Providerově neutrální jádro balíčku tím nezískává závislost na webovém frameworku.

## Lokální spuštění

Nejdříve spusťte ThreadMesh API a lokální Traefik, potom sestavte a spusťte dashboard:

```bash
docker compose up -d
docker compose -f compose.dashboard.yaml up -d --build
```

Otevřete `http://threadmesh.loc/dashboard/`. Záložní adresa dostupná pouze přes loopback je `http://127.0.0.1:8082/dashboard/`; hostitelský port lze změnit pomocí `THREADMESH_DASHBOARD_PORT`.

Dashboard načte `THREADMESH_API_TOKEN` z kořenového souboru `.env` a posílá ho pouze ze serverové části kontejneru na `http://api:8080`. Token se nevkládá do HTML ani do požadavků prohlížeče.

Samostatné UI zastavíte bez ovlivnění API, MCP serveru nebo databáze:

```bash
docker compose -f compose.dashboard.yaml down
```

## Funkce

- barevné odlišení kritických, vysoce důležitých, běžných, málo důležitých a nevyhodnocených zpráv;
- filtry období, stavu hodnocení, důležitosti a požadované akce;
- předmět, odesílatel, čas, kategorie, shrnutí hodnocení a doporučený krok;
- UTF-8 dekódování RFC 2047 předmětů, zobrazovaných jmen adres a názvů příloh, včetně dříve uložených zpráv při čtení;
- celý detail zprávy a metadata příloh;
- sandboxovaný náhled HTML zprávy s blokovanými skripty, formuláři, navigací a vzdálenými zdroji.

Dashboard je záměrně pouze pro čtení. Nehodnotí zprávy, nevytváří koncepty, nemění IMAP příznaky, neodesílá e-maily a nezpřístupňuje uložené přihlašovací údaje.

## Bezpečnostní hranice

Hlavičky a obsah e-mailu jsou nedůvěryhodný vstup. Latte escapuje všechny hodnoty v seznamu a metadatech. Původní HTML vrací pouze samostatný endpoint náhledu a načítá se do `iframe` bez oprávnění sandboxu. Odpověď náhledu nastavuje přísnou Content Security Policy, blokuje referrer, vypíná MIME sniffing a nepovoluje externí obrázky, které by mohly sloužit jako sledovací pixely.

Dodané nasazení je určené pouze pro lokální použití přes loopback. Dashboard, API, MCP endpoint ani databázový prohlížeč nezpřístupňujte do LAN nebo internetu bez autentizované HTTPS brány a výslovného bezpečnostního posouzení.

Dashboard je distribuovaný pod stejnou kořenovou MIT licencí jako ThreadMesh.
