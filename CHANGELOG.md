# Changelog

## acteo/api (nelansat)

- `$acteo->casa->pad`: verificarea poliței PAD a unei locuințe, după CNP-ul proprietarului și localitate, cu răspunsul de la PADROM. Metodele sunt cele de la documentele auto: `verifica()`, `porneste()` (cu `$callbackUrl` opțional), `rezultat()` și `asteapta()`. Cere abonamentul API Verificare poliță PAD și acordul proprietarului (`'acord' => true`).
- CNP-ul, acordul, data și `callbackUrl` sunt validate local, înainte de cerere, deci o greșeală nu consumă din cotă. Răspunsurile arată CNP-ul doar mascat (`cnpMascat`).
- `$acteo->webhooks->parseazaRezultatPad()` și `Webhooks::parseazaWebhookRezultatPad()` citesc rezultatul trimis la `callbackUrl`.
- `Validare::esteCnpValid()` verifică local forma unui CNP: data de naștere și cifra de control.
- Clase noi: `CererePolitaPad`, `PolitaPadPornita`, `RezultatPolitaPad`, `WebhookRezultatPad` și enum-ul `StatusPolitaPad`.

## acteo/api 0.5.0 (2026-11-03)

API-ul trimite acum codurile de eroare din contract. Dacă ai cod care compară `$e->cod` cu valorile vechi, schimbă-l după tabelul de mai jos. Clasele de eroare și statusurile HTTP rămân aceleași.

| Situație | Cod vechi | Cod nou |
|---|---|---|
| Limita zilnică a planului atinsă (429) | `COTA_ATINSA` la verificări, `LIMIT_ATINS` la VIN, numere și poze | `DAILY_LIMIT_REACHED` |
| Limita lunară sau plafonul consumului peste plan atins (429) | `COTA_ATINSA` la verificări, `LIMIT_ATINS` la VIN, numere și poze | `PLAN_LIMIT_REACHED` |
| DGPCI refuză numărul (422) | `NUMAR_RESPINS` | `INVALID_IDENTIFIER` |
| DGPCI nu răspunde la timp (504) | `TIMEOUT` | `UPSTREAM_TIMEOUT` |
| Verificarea la DGPCI eșuează (502) | `EROARE_DGPCI` | `UPSTREAM_UNAVAILABLE` |
| Verificare încheiată cu `status` `error` | `VERIFICARE_ESUATA` | `INVALID_IDENTIFIER`, `UPSTREAM_UNAVAILABLE`, `UPSTREAM_TIMEOUT`, `UPSTREAM_BLOCKED` sau `UPSTREAM_CHANGED` |

- Enum nou `Acteo\Enum\CodEroare`, cu toate codurile pe care le trimite API-ul; `CodEroare::valori()` le dă ca text. `CodEroare::ESEC_VERIFICARE` are motivele unei verificări eșuate. `cod` pe excepții rămâne `string`.
- `EroareVerificare::$cod` spune de ce a eșuat verificarea. `codSursa` rămâne și arată câmpul refuzat la `INVALID_IDENTIFIER`.
- Rezultatul `NotFound` (`VerificareFinala`) are `code` `NOT_FOUND`, iar `WebhookRezultat` are `code` la eroare și la `NotFound`. Ambele sunt opționale (`null` când lipsesc).
- Pornirea unei verificări sau a unei verificări de număr poate primi `EroareServer` 503 cu `UPSTREAM_BLOCKED` sau `UPSTREAM_CHANGED` când sursa oficială e oprită temporar. Cererea nu se taxează și nu se repetă singură; `retryAfterMs` spune când să revii.
- `DAILY_LIMIT_REACHED` vine cu `retryAfterMs` până la miezul nopții, ora României. `PLAN_LIMIT_REACHED` nu are `retryAfterMs`, pentru că nu se deblochează la miezul nopții.
- Un server mai vechi, fără coduri, dă în continuare `COTA_ATINSA` și `VERIFICARE_ESUATA`.

## acteo/api 0.4.1 (2026-10-31)

- Documentație: fiecare verificare interoghează sursa oficială. `porneste()` întoarce o verificare în curs, iar rezultatul vine prin `asteapta()`, `rezultat()` sau webhook. `checkedAt` e descris ca momentul verificării. Codul nu se schimbă.

## acteo/api 0.4.0 (2026-10-31)

- Corectură: `$acteo->webhooks->verificaNotificare()` (și `Webhooks::verificaWebhookNotificare()`) citește cheia din headerul `X-Acteo-Key`, cel trimis acum de API. Până acum căuta doar `X-Verificrca-Key`, așa că notificările reale erau respinse cu `CHEIE_INVALIDA`. Headerul vechi e citit în continuare când lipsește cel nou. `Webhooks::HEADER_CHEIE` e acum `'x-acteo-key'`, iar `Webhooks::HEADER_EVENIMENT` e `'x-acteo-event'`.
- `verificaNotificare()` acceptă și evenimentele celorlalte servicii: `dosar.eveniment`, `dosar.termen`, `firma.schimbare`, `firma.dosar`, `marca.schimbare`, `marca.expira`, `asigurare.schimbare` și `termen.expira`, fiecare cu clasa lui. Toate implementează interfața nouă `WebhookNotificare`, care e și tipul întors.
- `$acteo->auto->vin->decodeaza($vin)`: decodarea VIN.
- `$acteo->auto->numere->verifica($numar)`: dacă un număr de înmatriculare e liber la DGPCI, cu un timeout de cel puțin 150 de secunde pentru această cerere (pe clientul HTTP construit de SDK).
- `$acteo->auto->imagini->obtine()` și `asteapta()`: poza de studio a unei mașini.
- Răspunsurile 422 devin `EroareValidare`. `EroareServer` are `retryAfterMs`. Un 503 `OCUPAT` se reîncearcă după `Retry-After`. Un 5xx cu alt cod din partea API-ului nu se mai repetă la POST.

## acteo/api 0.3.0 (2026-10-29)

Pachetul `verificrca/sdk` continuă sub numele `acteo/api`, cu apelurile grupate pe domeniu și pe document. Vezi secțiunea „Vii de la verificrca/sdk?” din README.

- Client nou `Acteo`: `$acteo->auto->rca`, `$acteo->auto->itp`, `$acteo->auto->rovinieta` și `$acteo->auto->arr`, fiecare cu `verifica`, `porneste`, `rezultat` și `asteapta`. Fiecare document își cere exact identificatorii lui.
- La RCA, un text de 17 caractere fără I, O și Q e tratat ca VIN. Cu ambele date: `['numar' => ..., 'vin' => ...]` sau `new IdentificatorRca(...)`. Rovinieta primește `['numar' => ..., 'vin' => ..., 'serieTalon' => ...]` sau `new IdentificatorRovinieta(...)`.
- `rezultat()` și `asteapta()` primesc verificarea în curs sau direct identificatorul documentului.
- `$acteo->auto->flota` pentru vehiculele din cont și `$acteo->webhooks` pentru webhook-uri.
- Adresa implicită este `https://api.acteo.ro`. Cheia se citește din `ACTEO_API_KEY`, apoi din `VERIFICRCA_API_KEY`.
- Namespace-ul devine `Acteo\`. Numele vechi `VerificRca\...` rămân valabile, ca alias.
- `ActeoException`, clasa de bază a excepțiilor (vechiul nume `VerificRcaException` e alias al ei).
- Clientul `VerificRca` și apelurile de până acum funcționează în continuare, cu aceeași adresă implicită.

## 0.2.0 (2026-10-09)

- Verificarea rovinietei cere `serieTalon`, numărul certificatului de înmatriculare tipărit jos pe talon (ex: `I00639603F`). SDK-ul îl normalizează (majuscule, doar litere și cifre) și îl validează local: lipsă sau invalid înseamnă `EroareValidare` cu `camp` `serieTalon`, fără cerere pe rețea. La celelalte verificări e ignorat.
- `EroareVerificare` are proprietatea nouă `codSursa`, cu motivul trimis de API când sursa a refuzat datele: `serie_talon_invalida`, `vin_invalid` sau `numar_invalid` (`null` când sursa nu a răspuns la timp). `cod` rămâne `VERIFICARE_ESUATA`.
- `Vehicul` are `serieTalon` și `serieTalonRespinsa`; `VehiculNou` acceptă `serieTalon` opțional.
- Utilitare noi: `Validare::esteSerieTalonValida()` și `Validare::normalizeazaSerieTalon()`.

## 0.1.0 (nelansat)

Prima versiune.

- Client `VerificRca` cu `verificari->porneste`, `rezultat`, `asteapta` și `verificaSiAsteapta` pentru RCA, ITP, rovinietă și ARR.
- Gestionarea flotei: `vehicule->lista`, `adauga`, `sterge`.
- Webhook-uri: `Webhooks::parseazaWebhookRezultat` și `Webhooks::verificaWebhookNotificare` (cheie comparată în timp constant).
- Validare locală a numărului de înmatriculare și a VIN-ului, cu aceleași reguli ca API-ul.
- Excepții tipate, retry cu backoff exponențial și jitter, `Retry-After` respectat, niciun POST taxat repetat.
- Orice client HTTP PSR-18; Guzzle sau Symfony HttpClient folosite automat. PHP 8.1+.
