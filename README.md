# acteo/api: API verificare RCA, ITP, rovinietă și ARR pentru PHP

[![Packagist](https://img.shields.io/packagist/v/acteo/api.svg)](https://packagist.org/packages/acteo/api)
[![PHP](https://img.shields.io/packagist/dependency-v/acteo/api/php.svg)](https://packagist.org/packages/acteo/api)
[![licență MIT](https://img.shields.io/packagist/l/acteo/api.svg)](./LICENSE)

SDK-ul oficial PHP pentru [API-ul Acteo](https://acteo.ro/api-acces). Cu el verifici din cod dacă o mașină are RCA valabil, ITP la zi, rovinietă plătită sau copie conformă ARR, după numărul de înmatriculare sau seria de șasiu (VIN). Datele vin din sursele oficiale: baza AIDA pentru asigurarea RCA, RAR pentru inspecția tehnică periodică, CNAIR pentru rovinietă și ARR pentru transportul rutier.

Pe lângă verificările la cerere, SDK-ul gestionează vehiculele monitorizate din cont și citește webhook-urile trimise când un document se apropie de expirare. Dacă ai o firmă de leasing, rent-a-car sau curierat, un service auto ori pur și simplu o flotă, afli din timp ce RCA, ITP sau rovinietă expiră, înainte să vină amenda.

> Ai nevoie de o cheie API. O generezi din [contul tău Acteo](https://app.acteo.ro/api/chei), după ce alegi un plan de pe [pagina API](https://acteo.ro/api-acces).

```php
use Acteo\Acteo;

$acteo = new Acteo(apiKey: getenv('ACTEO_API_KEY') ?: null);

$rca = $acteo->auto->rca->verifica('B123ABC');
echo $rca->status->value, ' ', $rca->expiresAt?->format('Y-m-d'); // valid 2027-03-01
```

Apelurile sunt grupate pe domeniu și pe document, deci citești din cod ce verifici: `$acteo->auto->rca`, `$acteo->auto->itp`, `$acteo->auto->rovinieta`, `$acteo->auto->arr`, plus `$acteo->auto->flota` pentru vehiculele din cont și `$acteo->webhooks` pentru notificări.

## Cuprins

- [Ce face SDK-ul](#ce-face-sdk-ul)
- [Instalare](#instalare)
- [Pornire rapidă](#pornire-rapidă)
- [Ce identificator cere fiecare verificare](#ce-identificator-cere-fiecare-verificare)
- [Cum decurge o verificare](#cum-decurge-o-verificare)
- [Referință API](#referință-api)
- [Webhook-uri](#webhook-uri)
- [Erori](#erori)
- [Reîncercări, backoff și timeout](#reîncercări-backoff-și-timeout)
- [Cotă și limite](#cotă-și-limite)
- [Exemple](#exemple)
- [Vii de la verificrca/sdk?](#vii-de-la-verificrcasdk)
- [Întrebări frecvente](#întrebări-frecvente)

## Ce face SDK-ul

| Funcție | Apel | Ce primești |
|---|---|---|
| Verificare RCA după număr sau VIN | `$acteo->auto->rca->verifica()` | Starea poliței RCA și data expirării, din baza AIDA |
| Verificare ITP după VIN | `$acteo->auto->itp->verifica()` | Data până la care e valabilă inspecția tehnică, din RAR |
| Verificare rovinietă | `$acteo->auto->rovinieta->verifica()` | Dacă rovinieta e plătită și până când, din CNAIR |
| Verificare copie conformă ARR | `$acteo->auto->arr->verifica()` | Valabilitatea copiei conforme pentru transportatori |
| Gestionare flotă | `$acteo->auto->flota` | Adaugi, listezi și ștergi vehiculele monitorizate automat |
| Webhook-uri | `$acteo->webhooks` | Rezultatul verificării la URL-ul tău, alerte `document.expira` la 30, 14 și 3 zile |
| Validare locală | | Numărul de înmatriculare, VIN-ul și numărul talonului sunt verificate înainte de cerere, deci o greșeală de tastare nu consumă din cotă |
| Excepții tipate | | O clasă de excepție pentru fiecare situație, cu mesaj în română și `hint` de rezolvare |
| Reîncercări sigure | | Backoff exponențial cu jitter, `Retry-After` respectat, niciun POST taxat de două ori |

## Instalare

```bash
composer require acteo/api
```

SDK-ul cere PHP 8.1 sau mai nou și folosește orice client HTTP compatibil PSR-18. Dacă proiectul tău nu are unul, instalează Guzzle:

```bash
composer require guzzlehttp/guzzle
```

Cu Guzzle sau Symfony HttpClient instalate, SDK-ul își construiește singur clientul și aplică `timeoutMs`. Un client pe care îl dai tu prin `httpClient` își păstrează propriul timeout.

Cheia API o poți da direct în constructor sau prin variabila de mediu `ACTEO_API_KEY`:

```bash
export ACTEO_API_KEY=cheia_ta_api
```

Nu pune cheia în cod care ajunge în browser sau într-un repo public. Cheia dă acces la cota contului tău și la vehiculele din el.

## Pornire rapidă

### Verificare RCA după număr de înmatriculare

```php
use Acteo\Acteo;
use Acteo\Enum\StatusDocument;

$acteo = new Acteo(); // citește ACTEO_API_KEY

$rca = $acteo->auto->rca->verifica('B 123 ABC');

if ($rca->status === StatusDocument::Expired) {
    echo 'Mașina nu are RCA valabil.';
} elseif ($rca->status === StatusDocument::Warning) {
    echo 'RCA-ul expiră curând, pe ' . $rca->expiresAt?->format('d.m.Y');
}
```

Un text de 17 caractere fără literele I, O și Q e tratat ca VIN, orice altceva ca număr de înmatriculare. Pentru RCA îți recomandăm să trimiți ambele. Baza AIDA găsește polița mai sigur după seria de șasiu, iar uneori o poliță emisă pe VIN nu apare la căutarea după număr:

```php
use Acteo\Auto\IdentificatorRca;

$acteo->auto->rca->verifica(['numar' => 'B123ABC', 'vin' => 'WVWZZZ1JZXW000001']);
// sau, cu completare în IDE:
$acteo->auto->rca->verifica(new IdentificatorRca(numar: 'B123ABC', vin: 'WVWZZZ1JZXW000001'));
```

### Verificare ITP după serie de șasiu

```php
$itp = $acteo->auto->itp->verifica('WVWZZZ1JZXW000001');
echo 'ITP valabil până la ' . $itp->expiresAt?->format('d.m.Y');
```

### Verificare rovinietă

Rovinieta cere trei date: numărul de înmatriculare, seria de șasiu (VIN) și numărul certificatului de înmatriculare (`serieTalon`), tipărit jos pe talon. Sursa oficială le validează pe toate trei împreună.

```php
$rovinieta = $acteo->auto->rovinieta->verifica([
    'numar' => 'B123ABC',
    'vin' => 'WVWZZZ1JZXW000001',
    'serieTalon' => 'I00639603F',
]);
echo $rovinieta->status->value, ' ', $rovinieta->expiresAt?->format('d.m.Y');
```

Același lucru merge cu `new IdentificatorRovinieta(numar: ..., vin: ..., serieTalon: ...)` din `Acteo\Auto`. Spațiile, cratimele și literele mici din `serieTalon` sunt normalizate automat, deci `i 00639603-f` devine `I00639603F`.

Dacă sursa respinge datele (de exemplu, numărul talonului nu corespunde mașinii), primești `EroareVerificare` cu `codSursa` `serie_talon_invalida`. O reîncercare cu aceleași date nu ajută; corectează întâi câmpul indicat:

```php
use Acteo\Exception\EroareVerificare;

try {
    $acteo->auto->rovinieta->verifica(['numar' => 'B123ABC', 'vin' => $vin, 'serieTalon' => $talon]);
} catch (EroareVerificare $e) {
    if ($e->codSursa === 'serie_talon_invalida') {
        return cereUtilizatoruluiNumarulTalonului();
    }
    throw $e;
}
```

### Verificare copie conformă ARR

```php
$arr = $acteo->auto->arr->verifica('CJ45XYZ');
```

## Ce identificator cere fiecare verificare

Fiecare sursă oficială caută după alt identificator, așa că fiecare document își cere exact datele lui. SDK-ul aplică aceleași reguli ca API-ul și te oprește înainte de cerere dacă lipsește ceva.

| Apel | Ce primește | Sursa |
|---|---|---|
| `auto->rca->verifica($x)` | număr de înmatriculare **sau** VIN, ori `['numar' => ..., 'vin' => ...]` (recomandat) | AIDA / BAAR |
| `auto->itp->verifica($vin)` | VIN (17 caractere) | RAR |
| `auto->rovinieta->verifica(['numar' => ..., 'vin' => ..., 'serieTalon' => ...])` | toate trei, **obligatorii** | CNAIR |
| `auto->arr->verifica($numar)` | număr de înmatriculare | ARR |

Formatele de număr acceptate: București (`B123ABC`, `B12ABC`), județe (`CJ45XYZ`, `IS08BCD`), corp diplomatic (`CD123A`) și numere temporare (`TC1234AB`). Spațiile și cratimele sunt eliminate automat, deci `b-123 abc` devine `B123ABC`. VIN-ul trebuie să aibă exact 17 caractere, fără literele I, O și Q (standardul ISO 3779).

`serieTalon` este numărul certificatului de înmatriculare, de forma `I00639603F`. SDK-ul păstrează doar literele și cifrele, cu majuscule, și cere între 6 și 20 de caractere, cu cel puțin o cifră. Doar sursa oficială poate confirma că numărul aparține mașinii.

O cheie scrisă greșit în array (de exemplu `'numr'`) e respinsă local cu `EroareValidare`, fără cerere pe rețea.

## Cum decurge o verificare

1. **Cerere** (`porneste`). Dacă există un rezultat recent pentru mașina respectivă, îl primești imediat. Altfel verificarea intră în coadă și primești un obiect `VerificareInCurs`.
2. **Interogarea sursei.** Sursa oficială e consultată în fundal. Dacă nu răspunde în 4 minute, verificarea se încheie cu eroare.
3. **Rezultatul** ajunge la tine pe una din trei căi:
   - `verifica()` face tot drumul și întoarce direct rezultatul final;
   - `rezultat()` sau `asteapta()` fac polling, fără să consume din cotă;
   - webhook: dacă trimiți `callbackUrl` la `porneste()`, API-ul face POST cu rezultatul la adresa ta.

O verificare consumă din cota planului doar când sursa oficială a răspuns, inclusiv atunci când refuză datele trimise. Dacă sursa nu răspunde, nu consumi nimic. Interogările de rezultat sunt gratuite.

### Statusuri

Rezultatul final (`VerificareFinala`) are `status` de tip `StatusDocument`:

| `status` | Înseamnă | `expiresAt` |
|---|---|---|
| `StatusDocument::Valid` | Document valabil | data expirării |
| `StatusDocument::Warning` | Expiră curând: RCA în 14 zile, ITP în 30, rovinietă în 7, ARR în 14 | data expirării |
| `StatusDocument::Expired` | Expirat sau inexistent, confirmat de sursă | data expirării sau `null` |
| `StatusDocument::NotFound` | Sursa nu a găsit vehiculul | `null` |

O verificare încă în curs e un obiect `VerificareInCurs` (`status` `processing`), nu un rezultat. Le deosebești cu `instanceof`.

Când verificarea se încheie cu eroare, SDK-ul aruncă `EroareVerificare`. Dacă sursa oficială doar nu a răspuns, `codSursa` e `null` și poți relua cererea. Dacă sursa a refuzat datele trimise, `codSursa` spune ce e greșit și reîncercarea nu ajută până nu corectezi câmpul:

| `codSursa` | Câmpul de corectat |
|---|---|
| `serie_talon_invalida` | `serieTalon`, numărul certificatului de înmatriculare |
| `vin_invalid` | `vin` |
| `numar_invalid` | numărul de înmatriculare |

## Referință API

### `new Acteo(...)`

Constructorul primește argumente numite, toate opționale:

| Argument | Tip | Implicit | Descriere |
|---|---|---|---|
| `apiKey` | `?string` | variabila `ACTEO_API_KEY` | Cheia API |
| `baseUrl` | `string` | `https://api.acteo.ro` | Adresa API-ului |
| `timeoutMs` | `int` | `30000` | Timeout pe fiecare cerere HTTP, aplicat clientului construit de SDK |
| `retry` | `array\|false` | `['incercari' => 3, 'intarziereInitialaMs' => 500, 'intarziereMaximaMs' => 10000]` | Politica de reîncercare; `false` o oprește |
| `httpClient` | `?ClientInterface` | Guzzle, Symfony HttpClient sau alt client PSR-18 găsit automat | Client propriu (proxy, teste) |
| `requestFactory`, `streamFactory` | PSR-17 | găsite automat | Fabrici PSR-17, dacă vrei să le alegi tu |
| `userAgent` | `?string` | | Adăugat la `User-Agent`, util în loguri |

Constructorul nu face nicio cerere pe rețea. Aruncă `EroareConfigurare` dacă lipsește cheia sau dacă o opțiune e invalidă.

### `auto-><document>->verifica($identificator, ?OptiuniAsteptare $optiuni = null)`

Disponibil pe `auto->rca`, `auto->itp`, `auto->rovinieta` și `auto->arr`. Pornește verificarea și așteaptă rezultatul final (`VerificareFinala`). E calea cea mai simplă pentru scripturi și joburi cron. Opțiuni: `intervalMs` (implicit 3000, minim 2000) și `timeoutMs` (implicit 5 minute). La depășire aruncă `EroareTimeoutVerificare`. Reîncercările se opresc la termen, așa că doar o cerere HTTP deja pornită îl poate depăși, cu cel mult timeout-ul ei.

```php
use Acteo\Cerere\OptiuniAsteptare;

$rca = $acteo->auto->rca->verifica('B123ABC', new OptiuniAsteptare(intervalMs: 5000, timeoutMs: 120000));
```

### `auto-><document>->porneste($identificator, ?string $callbackUrl = null)`

Pornește verificarea fără să aștepte. Întoarce fie rezultatul final (`VerificareFinala`, dacă există unul recent), fie un obiect `VerificareInCurs` pe care îl dai mai departe la `rezultat()` sau `asteapta()`.

```php
use Acteo\Model\VerificareInCurs;

$r = $acteo->auto->itp->porneste(
    'WVWZZZ1JZXW000001',
    callbackUrl: 'https://example.com/webhooks/acteo/9f3k2', // opțional
);

if ($r instanceof VerificareInCurs) {
    echo 'În coadă, requestId: ', $r->requestId;
    $final = $acteo->auto->itp->asteapta($r);
}
```

### `auto-><document>->rezultat($referinta)`

Interoghează o singură dată. Nu consumă din cotă. Primește obiectul `VerificareInCurs` întors de `porneste()` sau direct identificatorul documentului, de exemplu `$acteo->auto->rca->rezultat('B123ABC')`. La RCA cu număr și VIN, interogarea se face după VIN, ca la pornire. Un `VerificareInCurs` de la alt document (de exemplu ITP dat la `auto->rca`) e respins local cu `EroareValidare`.

### `auto-><document>->asteapta($referinta, ?OptiuniAsteptare $optiuni = null)`

Face polling până la rezultatul final, cu aceleași opțiuni ca `verifica()`. Nu consumă din cotă.

### `auto->flota->lista()`

Toate vehiculele din cont, cu datele de expirare `rcaExpiraLa`, `itpExpiraLa`, `rovinietaExpiraLa` și `arrExpiraLa` (ca `DateTimeImmutable` sau `null`). Câmpul `activ` e `true` cât timp vehiculul e verificat automat și primește notificări de expirare. `serieTalon` e numărul certificatului de înmatriculare (sau `null`), iar `serieTalonRespinsa` devine `true` când sursa oficială l-a respins; rovinieta nu se mai verifică până nu e corectat. Vehiculele din cont sunt verificate automat și periodic, deci lista îți dă o imagine la zi a flotei fără să consume din cota de verificări.

### `auto->flota->adauga(VehiculNou $vehicul)`

Adaugă un vehicul în monitorizare. Trimite cel puțin un identificator (`numarInmatriculare`, `serieSasiu`); opțional `serieTalon` și `arrMonitorizat`. ITP-ul se monitorizează doar cu VIN, iar rovinieta cere numărul, VIN-ul și `serieTalon`; fără numărul talonului, rovinieta nu se verifică.

```php
use Acteo\Cerere\VehiculNou;

foreach ($acteo->auto->flota->lista() as $v) {
    echo $v->numarInmatriculare, ' RCA: ', $v->rcaExpiraLa?->format('d.m.Y') ?? '-', PHP_EOL;
}

$v = $acteo->auto->flota->adauga(new VehiculNou(
    numarInmatriculare: 'B123ABC',
    serieSasiu: 'WVWZZZ1JZXW000001',
    serieTalon: 'I00639603F',
));
```

Poți primi `EroareInterzis` cu `cod` `LIMIT_ATINS` când ai atins numărul de mașini din plan, sau `EroareConflict` când vehiculul există deja.

### `auto->flota->sterge(string $id)`

Scoate vehiculul din cont. Aruncă `EroareNegasit` dacă ID-ul nu există în contul cheii.

Operațiile pe flotă nu consumă din cota de verificări, dar cer un plan activ.

### Utilitare de validare

```php
use Acteo\Validare;

Validare::esteNumarInmatriculareValid('CJ 45 XYZ'); // true
Validare::esteVinValid('WVWZZZ1JZXW00000I');        // false: litera I nu e permisă în VIN
Validare::esteSerieTalonValida('I00639603F');       // true
Validare::normalizeaza(' b-123 abc ');              // 'B123ABC'
Validare::normalizeazaSerieTalon(' i 00639603-f '); // 'I00639603F'
```

Le poți folosi pentru formularele tale, ca să validezi datele înainte să ajungă la server.

### Constante

| Constantă | Valoare |
|---|---|
| `Validare::TIPURI_VERIFICARE` | `['rca', 'itp', 'rovinieta', 'arr']`, util pentru dropdown-uri și validare |
| `Webhooks::HEADER_CHEIE` | `'x-verificrca-key'`, headerul cu cheia de webhook |
| `Webhooks::HEADER_EVENIMENT` | `'x-verificrca-event'`, headerul cu numele evenimentului |
| `Acteo::URL_IMPLICIT` | `'https://api.acteo.ro'` |
| `Acteo::VERSIUNE_SDK` | versiunea pachetului, trimisă în `User-Agent` |

### Documentația tipurilor

Fiecare metodă, câmp și excepție are PHPDoc, cu parametri, valoare întoarsă și erorile posibile. IDE-ul (PhpStorm, VS Code cu Intelephense) îl arată la hover, iar PHPStan îl folosește la analiză, inclusiv formele de array acceptate ca identificator.

## Webhook-uri

API-ul trimite două feluri de webhook. SDK-ul le validează strict și îți dă obiecte tipate, cu datele ca `DateTimeImmutable`.

### Rezultatul unei verificări (`callbackUrl`)

Când trimiți `callbackUrl` la `porneste()`, primești un POST cu rezultatul imediat ce e gata. Dacă sursa nu răspunde, primești `status` `StatusWebhook::Error`.

```php
use Acteo\Enum\StatusWebhook;

// public/webhooks/acteo-9f3k2.php
$r = $acteo->webhooks->parseazaRezultat(file_get_contents('php://input'));
if ($r->status !== StatusWebhook::Error) {
    salveaza($r->requestId, $r->result?->expiresAt);
}
http_response_code(200);
```

Pune un segment greu de ghicit în URL, păstrează-l pe HTTPS și verifică dacă `requestId`-ul primit corespunde celui întors de `porneste()` înainte să te bazezi pe conținut.

### Notificări de expirare (`document.expira`)

Pe planurile care includ webhook-uri, configurezi un URL în dashboard și primești câte un eveniment când un document al unui vehicul monitorizat intră într-un prag de expirare. Pentru RCA, ITP și rovinietă pragurile sunt 30, 14 și 3 zile, iar pentru ARR 60, 50 și 45 de zile. Fiecare cerere vine cu cheia contului în headerul `X-Verificrca-Key`. SDK-ul o compară în timp constant, ca să nu dezvăluie nimic prin durata comparației:

```php
use Acteo\Exception\EroareWebhook;
use Acteo\Model\WebhookDocumentExpira;

try {
    $ev = $acteo->webhooks->verificaNotificare(file_get_contents('php://input'), getallheaders(), (string) getenv('ACTEO_WEBHOOK_KEY'));
    if ($ev instanceof WebhookDocumentExpira) {
        trimiteAlerta("{$ev->vehicul->numarInmatriculare}: {$ev->document->value} expiră în {$ev->zileRamase} zile");
    }
    http_response_code(200);
} catch (EroareWebhook) {
    http_response_code(401);
}
```

Headerele pot veni din `getallheaders()`, din `$_SERVER`, din `$request->headers->all()` (Laravel, Symfony) sau dintr-o cerere PSR-7. În Laravel arată așa (exclude ruta din verificarea CSRF):

```php
use Acteo\Acteo;
use Acteo\Exception\EroareWebhook;
use Acteo\Model\WebhookDocumentExpira;
use Illuminate\Http\Request;

Route::post('/webhooks/acteo', function (Request $request, Acteo $acteo) {
    try {
        $ev = $acteo->webhooks->verificaNotificare($request->getContent(), $request->headers->all(), config('services.acteo.webhook_key'));
    } catch (EroareWebhook) {
        abort(401);
    }
    if ($ev instanceof WebhookDocumentExpira) {
        dispatch(new TrimiteAlertaExpirare($ev->vehicul->id, $ev->document->value, $ev->zileRamase));
    }

    return response()->noContent();
});
```

Folosește `$ev->id` pentru deduplicare. Un eveniment poate ajunge de mai multe ori dacă serverul tău nu răspunde cu 2xx. Butonul de test din dashboard trimite un `WebhookTest` (`event` `webhook.test`).

### Verificarea în joburi de coadă

`VerificareInCurs` se salvează cu `json_encode()` și se reface cu `VerificareInCurs::fromArray()`. Așa pornești verificarea într-un proces și citești rezultatul în altul:

```php
use Acteo\Model\VerificareInCurs;

$r = $acteo->auto->itp->porneste('WVWZZZ1JZXW000001');
if ($r instanceof VerificareInCurs) {
    $salvat = json_encode($r); // în coadă, în baza de date
}

// mai târziu, în alt proces
$r = $acteo->auto->itp->rezultat(VerificareInCurs::fromArray(json_decode($salvat, true)));
```

## Erori

Toate excepțiile moștenesc `ActeoException`, care la rândul ei moștenește `RuntimeException`. Fiecare are `getMessage()` (în română), plus proprietățile `status`, `cod`, `hint` și `raspuns` când vin de la API.

| Clasă | Când apare | Câmpuri utile |
|---|---|---|
| `EroareConfigurare` | Cheie lipsă sau opțiuni invalide | `hint` |
| `EroareValidare` | Date greșite, local sau HTTP 400 | `camp`, `acceptate` |
| `EroareAutentificare` | 401: cheie invalidă sau dezactivată | `hint` |
| `EroarePlanInactiv` | 402: fără abonament activ | `hint` |
| `EroareInterzis` | 403: `LIMIT_ATINS`, `ARR_NECESITA_PLAN` | `cod`, `limita` |
| `EroareNegasit` | 404: vehicul inexistent în cont | |
| `EroareConflict` | 409: `VEHICUL_EXISTENT` | |
| `EroareLimita` | 429: cotă, plafon de flotă sau polling prea des | `cod`, `retryAfterMs`, `utilizareZi`, `utilizareLuna` |
| `EroareServer` | 5xx | `status` |
| `EroareHttp` | Alt status HTTP neașteptat | `status` |
| `EroareRetea` | Fără răspuns sau timeout | `timeout`, `neajunsa` |
| `EroareRaspunsInvalid` | Răspuns cu formă neașteptată | `raspuns` |
| `EroareVerificare` | Sursa oficială nu a putut fi interogată sau a refuzat datele | `codSursa` |
| `EroareTimeoutVerificare` | `asteapta()` a depășit `timeoutMs` | |
| `EroareWebhook` | Cheie greșită sau payload invalid | `cod` |

Toate sunt în namespace-ul `Acteo\Exception`.

```php
use Acteo\Exception\ActeoException;
use Acteo\Exception\EroareLimita;
use Acteo\Exception\EroareValidare;

try {
    $acteo->auto->rca->porneste($numar);
} catch (EroareValidare $e) {
    return arataUtilizatorului($e->getMessage());
} catch (EroareLimita $e) {
    if ($e->cod === 'COTA_ATINSA') {
        return amanaPanaMaine();
    }
    throw $e;
} catch (ActeoException $e) {
    $logger->error($e->getMessage(), ['cod' => $e->cod, 'hint' => $e->hint]);
    throw $e;
}
```

`EroareVerificare` are mereu `cod` `VERIFICARE_ESUATA`; motivul refuzului e în `codSursa` (tabelul de la [Statusuri](#statusuri)).

## Reîncercări, backoff și timeout

Erorile trecătoare sunt reîncercate automat, cu backoff exponențial (x2) și jitter, plafonat la `intarziereMaximaMs`. Când serverul trimite `Retry-After`, pauza nu coboară sub valoarea cerută.

SDK-ul nu trimite niciodată de două ori o verificare taxată.

| Situație | GET / DELETE | POST |
|---|---|---|
| Rețea căzută, timeout | se reîncearcă | nu (cererea poate să fi ajuns) |
| Redirect (3xx) | nu, `EroareHttp` cu `hint` spre adresa canonică | nu |
| DNS, conexiune refuzată | se reîncearcă | se reîncearcă (sigur nu a ajuns) |
| 502, 503, 504 | se reîncearcă | se reîncearcă (proxy, nu aplicația) |
| 500 | se reîncearcă | nu |
| 429 la polling | se reîncearcă după `Retry-After` | |
| 429 cotă sau plafon flotă | nu | nu |
| 4xx | nu | nu |

```php
$acteo = new Acteo(
    timeoutMs: 15000,
    retry: ['incercari' => 5, 'intarziereInitialaMs' => 250, 'intarziereMaximaMs' => 8000],
);
```

## Cotă și limite

Fiecare verificare consumă o unitate din cota zilnică și lunară a planului, dar numai când sursa oficială a răspuns, inclusiv atunci când refuză datele trimise. Dacă mașina a fost verificată de curând, primești imediat rezultatul existent, iar cererea consumă din cotă. Dacă sursa nu răspunde, nu consumi nimic. Verificările încă în curs ocupă cota până la răspuns, ca să nu treci de limită cu cereri trimise în paralel.

Rezultatele au proprietatea `limite`, completată din headerele `X-RateLimit-*`:

```php
$r = $acteo->auto->rca->porneste('B123ABC');
echo $r->limite?->zi->ramase;   // 287
echo $r->limite?->luna->limita; // 10000
```

Cota zilnică se resetează la miezul nopții, ora României. Limitele fiecărui plan le găsești pe [pagina API](https://acteo.ro/api-acces).

## Exemple

Folderul [`examples/`](./examples) conține scripturi gata de rulat:

- [`verificare-rca.php`](./examples/verificare-rca.php): verificare RCA cu număr și VIN, tratarea erorilor;
- [`flota.php`](./examples/flota.php): raport cu RCA, ITP, rovinietă și ARR care expiră în 30 de zile, pentru toată flota;
- [`webhook-server.php`](./examples/webhook-server.php): primește ambele tipuri de webhook, pornit cu `php -S`.

### Verificare în masă

```php
use Acteo\Exception\ActeoException;

foreach (['B123ABC', 'CJ45XYZ', 'IS08BCD'] as $numar) {
    try {
        $r = $acteo->auto->rca->verifica($numar);
        echo $numar, ': ', $r->status->value, PHP_EOL;
    } catch (ActeoException $e) {
        echo $numar, ': ', $e->getMessage(), PHP_EOL;
    }
}
```

Pentru liste mari, adaugă vehiculele în cont cu `auto->flota->adauga()`. Sunt monitorizate automat și primești notificările de expirare, fără să consumi din cota de verificări.

## Vii de la verificrca/sdk?

Pachetul `verificrca/sdk` s-a mutat la `acteo/api`. Instalezi pachetul nou cu `composer require acteo/api` și schimbi apelurile:

| Înainte | Acum |
|---|---|
| namespace `VerificRca\...` | namespace `Acteo\...` (numele vechi merg în continuare, ca alias) |
| `new VerificRca()` | `new Acteo()` |
| `VERIFICRCA_API_KEY` | `ACTEO_API_KEY` (cea veche e citită în continuare, dacă lipsește cea nouă) |
| `verificari->verificaSiAsteapta(new CerereVerificare(TipVerificare::Rca, $query))` | `auto->rca->verifica($query)` |
| `verificari->verificaSiAsteapta(new CerereVerificare(TipVerificare::Rca, $numar, $vin))` | `auto->rca->verifica(['numar' => $numar, 'vin' => $vin])` |
| `verificari->verificaSiAsteapta(new CerereVerificare(TipVerificare::Itp, $vin))` | `auto->itp->verifica($vin)` |
| `verificari->verificaSiAsteapta(new CerereVerificare(TipVerificare::Rovinieta, $numar, $vin, serieTalon: $talon))` | `auto->rovinieta->verifica(['numar' => $numar, 'vin' => $vin, 'serieTalon' => $talon])` |
| `verificari->porneste(...)` / `rezultat(...)` / `asteapta(...)` | `auto-><document>->porneste` / `rezultat` / `asteapta` |
| `new ReferintaVerificare(TipVerificare::Rca, $query)` | identificatorul direct: `auto->rca->rezultat($query)` |
| `vehicule->lista()` / `adauga()` / `sterge()` | `auto->flota->lista()` / `adauga()` / `sterge()` |
| `Webhooks::parseazaWebhookRezultat()` / `Webhooks::verificaWebhookNotificare()` | `webhooks->parseazaRezultat()` / `webhooks->verificaNotificare()` |
| `VerificRcaException` | `ActeoException` |

Modelele, enum-urile, excepțiile și utilitarele de validare au rămas aceleași, doar sub namespace-ul `Acteo\`.

## Întrebări frecvente

### Cum verific RCA-ul unei mașini din PHP?

Instalezi pachetul cu `composer require acteo/api`, creezi un client cu cheia API și apelezi `$acteo->auto->rca->verifica('B123ABC')`. Primești statusul poliței și data expirării, din baza de date AIDA.

### Pot verifica ITP-ul după numărul de înmatriculare?

Nu. RAR caută inspecția tehnică periodică doar după seria de șasiu (VIN), așa că SDK-ul respinge cererea înainte să plece, fără să consume din cotă. VIN-ul îl găsești în talon, la rubrica E.

### Unde găsesc numărul certificatului de înmatriculare?

Pe talon (certificatul de înmatriculare), jos, la rubrica „Numărul certificatului”. Conține litere și cifre, de exemplu `I00639603F`. Nu îl confunda cu seria de șasiu (VIN), care e la rubrica E și are 17 caractere. SDK-ul acceptă numărul și cu spații sau cu litere mici și îl aduce singur la forma corectă.

### Cum verific rovinieta unei mașini din PHP?

Apelezi `$acteo->auto->rovinieta->verifica()` cu numărul de înmatriculare, VIN-ul și numărul certificatului de înmatriculare. Sursa oficială le validează împreună, deci toate trei sunt obligatorii. Dacă lipsește una, SDK-ul aruncă `EroareValidare` înainte de cerere, fără să consume din cotă. Pentru vehiculele din cont, adaugă `serieTalon` la `auto->flota->adauga()`, altfel rovinieta lor nu se monitorizează. Pentru o verificare manuală, folosește pagina de [verificare rovinietă](https://acteo.ro/verificare-rovinieta).

### De unde vin datele?

Din sursele oficiale: AIDA (baza de date a asigurărilor RCA administrată de BAAR), Registrul Auto Român pentru ITP, CNAIR pentru rovinietă și Autoritatea Rutieră Română pentru copia conformă. Nu estimăm nimic: fiecare rezultat e exact ce a răspuns sursa.

### De ce o poliță RCA nouă apare ca expirată?

Asigurătorul are nevoie de timp ca să înregistreze polița nouă în AIDA, de obicei între 1 și 7 zile. În acest interval verificarea poate arăta polița veche. Detalii despre [amenzile pentru RCA expirat](https://acteo.ro/amenzi/rca).

### Merge cu Laravel, Symfony sau WordPress?

Da. E un pachet Composer fără dependențe de framework. În Laravel îl înregistrezi ca singleton în `AppServiceProvider`, iar în WordPress îl încarci din `vendor/autoload.php` al pluginului sau al temei. Cheia API rămâne pe server.

### Există SDK pentru Node.js sau Python?

Da, cu aceleași apeluri: [`@acteo/api`](https://github.com/acteoro/sdk_javascript) pentru Node.js și TypeScript și [`acteo`](https://github.com/acteoro/sdk_python) pentru Python. [Documentația API](https://acteo.ro/api-acces) are și exemple pentru cURL.

## Linkuri utile

- [Documentația API Acteo](https://acteo.ro/api-acces)
- [Verificare RCA online](https://acteo.ro/verificare-rca)
- [Verificare ITP online](https://acteo.ro/verificare-itp)
- [Verificare rovinietă online](https://acteo.ro/verificare-rovinieta)
- [Verificare copie conformă ARR](https://acteo.ro/verificare-arr)
- [Monitorizare flote auto](https://acteo.ro/flote)
- [Changelog](./CHANGELOG.md)

## Dezvoltare

```bash
composer install
composer test        # teste unitare, fără rețea
composer stan        # PHPStan, nivel max
composer cs          # stilul de cod (PER-CS)
composer test:e2e    # teste pe API-ul real
```

Testele e2e citesc configurarea din `.env.e2e`, fișier ignorat de git. Pornești de la șablon:

```bash
cp .env.e2e.example .env.e2e
```

Fără cheie rulează doar testul de autentificare. Cu `ACTEO_API_KEY` se adaugă listarea vehiculelor, care e gratuită. Verificările reale (`ACTEO_E2E_VERIFICARI=1`) consumă din cotă, iar testul de flotă (`ACTEO_E2E_FLOTA=1`) adaugă și șterge un vehicul. Amândouă pornesc doar dacă le activezi explicit.

## Licență

[MIT](./LICENSE), © Acteo.ro
