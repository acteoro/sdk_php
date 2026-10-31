# Changelog

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
