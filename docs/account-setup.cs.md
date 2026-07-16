# Nastavení IMAP účtu ve Windows

Interaktivní PowerShell průvodce přidá IMAP účet, aniž by se heslo objevilo v historii shellu, argumentech příkazu, JSON souboru nebo chatu. Průvodce volá lokální ThreadMesh API chráněné bearer tokenem; API heslo před uložením do SQLite zašifruje.

## Spuštění průvodce

Spusťte ThreadMesh API a nastavte `THREADMESH_API_TOKEN`. Při použití Dockeru ponechte token v souboru `.env` v kořeni repozitáře a spusťte stack podle [Docker návodu](docker.cs.md).

Z kořene repozitáře spusťte:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File .\scripts\configure-imap-account.ps1
```

Průvodce používá `http://127.0.0.1:8080` a načte token z aktuálního prostředí nebo z lokálního `.env`. Vlastní umístění lze zadat parametry:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File .\scripts\configure-imap-account.ps1 `
  -ApiUrl http://127.0.0.1:18080 `
  -EnvFile C:\cesta\k\threadmesh.env
```

## Co průvodce provede

1. Vyžádá si ID a název účtu, IMAP server, port, zabezpečení, ověřování certifikátu a uživatelské jméno.
2. Načte IMAP heslo nebo app password pomocí skrytého vstupu.
3. Uloží účet přes lokální API a otestuje IMAP připojení.
4. Načte složky a nechá vybrat přesnou serverovou složku pro koncepty odpovědí.
5. Nechá vybrat jednu nebo více složek pro inkrementální synchronizaci.
6. Vybrané složky inicializuje až po zadání textu `INITIALIZE`.

Inicializace začne na aktuálním nejvyšším UID každé vybrané složky. Starší zprávy se neimportují; synchronizují se až zprávy doručené po inicializaci. Nejbezpečnější výchozí volbou je pouze `INBOX`. Odeslanou poštu, koš, spam a archivní složky nevybírejte, pokud je ThreadMesh nemá záměrně zpracovávat.

Stejné ID účtu lze použít při opakovaném spuštění pro změnu hesla nebo nastavení připojení. Neúspěšný test ponechá šifrovaný záznam účtu uložený, aby jej bylo možné dalším spuštěním opravit.

## Bezpečnostní poznámky

- Průvodce spouštějte na stejném důvěryhodném počítači jako ThreadMesh.
- API ponechte na loopback rozhraní nebo za autentizovanou HTTPS bránou.
- Pokud poskytovatel podporuje app password, upřednostněte jej před hlavním heslem.
- Heslo nikdy nevkládejte do argumentu příkazu, verzovaného souboru, kopírovaného API požadavku ani zprávy podpoře.
- Chraňte `.env`, SQLite databázi a `THREADMESH_MASTER_KEY`; bez master key nelze hesla v databázi dešifrovat.

Pro programovou správu účtů použijte [HTTP API](api.md).
