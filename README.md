# verificrca/sdk: API verificare RCA, ITP, rovinietă și ARR pentru PHP

[![Packagist](https://img.shields.io/packagist/v/verificrca/sdk.svg)](https://packagist.org/packages/verificrca/sdk)
[![PHP](https://img.shields.io/packagist/dependency-v/verificrca/sdk/php.svg)](https://packagist.org/packages/verificrca/sdk)
[![licență MIT](https://img.shields.io/packagist/l/verificrca/sdk.svg)](./LICENSE)

SDK-ul oficial PHP pentru [API-ul verificrca.ro](https://www.verificrca.ro/api-acces). Cu el verifici din cod dacă o mașină are RCA valabil, ITP la zi, rovinietă plătită sau copie conformă ARR, după numărul de înmatriculare sau seria de șasiu (VIN). Datele vin din sursele oficiale: baza AIDA pentru asigurarea RCA, RAR pentru inspecția tehnică periodică, CNAIR pentru rovinietă și ARR pentru transportul rutier.

Pe lângă verificările la cerere, SDK-ul gestionează vehiculele monitorizate din cont și citește webhook-urile trimise când un document se apropie de expirare. Dacă ai o firmă de leasing, rent-a-car sau curierat, un service auto ori pur și simplu o flotă, afli din timp ce RCA, ITP sau rovinietă expiră, înainte să vină amenda.

> Ai nevoie de o cheie API (`vrca_...`). O generezi din [contul tău verificrca.ro](https://www.verificrca.ro/dashboard/api), după ce alegi un plan de pe [pagina API](https://www.verificrca.ro/api-acces).

```php
use VerificRca\Cerere\CerereVerificare;
use VerificRca\Enum\TipVerificare;
use VerificRca\VerificRca;

$vrca = new VerificRca(apiKey: getenv('VERIFICRCA_API_KEY') ?: null);

$rca = $vrca->verificari->verificaSiAsteapta(new CerereVerificare(TipVerificare::Rca, 'B123ABC'));
echo $rca->status->value, ' ', $rca->expiresAt?->format('Y-m-d'); // valid 2027-03-01
```

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
- [Întrebări frecvente](#întrebări-frecvente)

## Ce face SDK-ul

| Funcție | Ce primești |
|---|---|
| Verificare RCA după număr sau VIN | Starea poliței RCA și data expirării, din baza AIDA |
| Verificare ITP după VIN | Data până la care e valabilă inspecția tehnică, din RAR |
| Verificare rovinietă | Dacă rovinieta e plătită și până când, din CNAIR |
| Verificare copie conformă ARR | Valabilitatea copiei conforme pentru transportatori |
| Gestionare flotă | Adaugi, listezi și ștergi vehiculele monitorizate automat |
| Webhook-uri | Rezultatul verificării la URL-ul tău, alerte `document.expira` la 30, 14 și 3 zile |
| Validare locală | Numărul de înmatriculare și VIN-ul sunt verificate înainte de cerere, deci o greșeală de tastare nu consumă din cotă |
| Excepții tipate | O clasă de excepție pentru fiecare situație, cu mesaj în română și `hint` de rezolvare |
| Reîncercări sigure | Backoff exponențial cu jitter, `Retry-After` respectat, niciun POST taxat de două ori |

## Instalare

```bash
composer require verificrca/sdk
```

SDK-ul cere PHP 8.1 sau mai nou și folosește orice client HTTP compatibil PSR-18. Dacă proiectul tău nu are unul, instalează Guzzle:

```bash
composer require guzzlehttp/guzzle
```

Cu Guzzle sau Symfony HttpClient instalate, SDK-ul își construiește singur clientul și aplică `timeoutMs`. Un client pe care îl dai tu prin `httpClient` își păstrează propriul timeout.

Cheia API o poți da direct în constructor sau prin variabila de mediu `VERIFICRCA_API_KEY`:

```bash
export VERIFICRCA_API_KEY=vrca_xxxxxxxxxxxxxxxx
```

Nu pune cheia în cod care ajunge în browser sau într-un repo public. Cheia dă acces la cota contului tău și la vehiculele din el.

## Pornire rapidă

### Verificare RCA după număr de înmatriculare

```php
use VerificRca\Cerere\CerereVerificare;
use VerificRca\Enum\StatusDocument;
use VerificRca\Enum\TipVerificare;
use VerificRca\VerificRca;

$vrca = new VerificRca(); // citește VERIFICRCA_API_KEY

$rca = $vrca->verificari->verificaSiAsteapta(new CerereVerificare(TipVerificare::Rca, 'B 123 ABC'));

if ($rca->status === StatusDocument::Expired) {
    echo 'Mașina nu are RCA valabil.';
} elseif ($rca->status === StatusDocument::Warning) {
    echo 'RCA-ul expiră curând, pe ' . $rca->expiresAt?->format('d.m.Y');
}
```

Pentru RCA îți recomandăm să trimiți și VIN-ul. Baza AIDA găsește polița mai sigur după seria de șasiu, iar uneori o poliță emisă pe VIN nu apare la căutarea după număr:

```php
$vrca->verificari->verificaSiAsteapta(new CerereVerificare(TipVerificare::Rca, 'B123ABC', serieSasiu: 'WVWZZZ1JZXW000001'));
```

### Verificare ITP după serie de șasiu

```php
$itp = $vrca->verificari->verificaSiAsteapta(new CerereVerificare(TipVerificare::Itp, 'WVWZZZ1JZXW000001'));
echo 'ITP valabil până la ' . $itp->expiresAt?->format('d.m.Y');
```

### Verificare rovinietă

```php
$rovinieta = $vrca->verificari->verificaSiAsteapta(new CerereVerificare(
    tip: TipVerificare::Rovinieta,
    query: 'B123ABC',
    serieSasiu: 'WVWZZZ1JZXW000001',
));
```

### Verificare copie conformă ARR

```php
$arr = $vrca->verificari->verificaSiAsteapta(new CerereVerificare(TipVerificare::Arr, 'CJ45XYZ'));
```

Poți da `tip` și ca text (`'rca'`, `'itp'`, `'rovinieta'`, `'arr'`), de exemplu direct din valoarea trimisă de un formular.

## Ce identificator cere fiecare verificare

Fiecare sursă oficială caută după alt identificator. SDK-ul aplică aceleași reguli ca API-ul și te oprește înainte de cerere dacă lipsește ceva.

| `tip` | `query` | `serieSasiu` | Sursa |
|---|---|---|---|
| `rca` | număr de înmatriculare **sau** VIN | opțional, recomandat | AIDA / BAAR |
| `itp` | VIN (17 caractere) | nu | RAR |
| `rovinieta` | număr de înmatriculare | **obligatoriu** | CNAIR |
| `arr` | număr de înmatriculare | nu | ARR |

Formatele de număr acceptate: București (`B123ABC`, `B12ABC`), județe (`CJ45XYZ`, `IS08BCD`), corp diplomatic (`CD123A`) și numere temporare (`TC1234AB`). Spațiile și cratimele sunt eliminate automat, deci `b-123 abc` devine `B123ABC`. VIN-ul trebuie să aibă exact 17 caractere, fără literele I, O și Q (standardul ISO 3779).

## Cum decurge o verificare

1. **Cerere** (`porneste`). Dacă există un rezultat recent pentru mașina respectivă, îl primești imediat. Altfel verificarea intră în coadă și primești un obiect `VerificareInCurs`.
2. **Interogarea sursei.** Sursa oficială e consultată în fundal. Dacă nu răspunde în 4 minute, verificarea se încheie cu eroare.
3. **Rezultatul** ajunge la tine pe una din trei căi:
   - `verificaSiAsteapta()` face tot drumul și întoarce direct rezultatul final;
   - `rezultat()` sau `asteapta()` fac polling, fără să consume din cotă;
   - webhook: dacă trimiți `callbackUrl`, API-ul face POST cu rezultatul la adresa ta.

Doar pasul 1 consumă din cota planului. Interogările de rezultat sunt gratuite.

### Statusuri

Rezultatul final (`VerificareFinala`) are `status` de tip `StatusDocument`:

| `status` | Înseamnă | `expiresAt` |
|---|---|---|
| `StatusDocument::Valid` | Document valabil | data expirării |
| `StatusDocument::Warning` | Expiră curând: RCA în 14 zile, ITP în 30, rovinietă în 7, ARR în 14 | data expirării |
| `StatusDocument::Expired` | Expirat sau inexistent, confirmat de sursă | data expirării sau `null` |
| `StatusDocument::NotFound` | Sursa nu a găsit vehiculul | `null` |

O verificare încă în curs e un obiect `VerificareInCurs`, nu un rezultat. Le deosebești cu `instanceof`. Când sursa oficială nu răspunde, SDK-ul aruncă `EroareVerificare` și poți relua cererea.

## Referință API

### `new VerificRca(...)`

Constructorul primește argumente numite, toate opționale:

| Argument | Tip | Implicit | Descriere |
|---|---|---|---|
| `apiKey` | `?string` | variabila `VERIFICRCA_API_KEY` | Cheia API `vrca_...` |
| `baseUrl` | `string` | `https://www.verificrca.ro` | Adresa API-ului |
| `timeoutMs` | `int` | `30000` | Timeout pe fiecare cerere HTTP, aplicat clientului construit de SDK |
| `retry` | `array\|false` | `['incercari' => 3, 'intarziereInitialaMs' => 500, 'intarziereMaximaMs' => 10000]` | Politica de reîncercare; `false` o oprește |
| `httpClient` | `?ClientInterface` | Guzzle, Symfony HttpClient sau alt client PSR-18 găsit automat | Client propriu (proxy, teste) |
| `requestFactory`, `streamFactory` | PSR-17 | găsite automat | Fabrici PSR-17, dacă vrei să le alegi tu |
| `userAgent` | `?string` | | Adăugat la `User-Agent`, util în loguri |

Constructorul nu face nicio cerere pe rețea. Aruncă `EroareConfigurare` dacă lipsește cheia sau dacă o opțiune e invalidă.

### `verificari->porneste(CerereVerificare $cerere)`

Pornește o verificare și **consumă o unitate din cotă**. Întoarce fie rezultatul final (`VerificareFinala`, dacă există unul recent), fie un obiect `VerificareInCurs` pe care îl poți da mai departe la `rezultat()` sau `asteapta()`.

```php
use VerificRca\Model\VerificareInCurs;

$r = $vrca->verificari->porneste(new CerereVerificare(
    tip: TipVerificare::Itp,
    query: 'WVWZZZ1JZXW000001',
    callbackUrl: 'https://example.com/webhooks/verificrca/9f3k2', // opțional
));

if ($r instanceof VerificareInCurs) {
    echo 'În coadă, requestId: ', $r->requestId;
    $final = $vrca->verificari->asteapta($r);
}
```

### `verificari->rezultat($referinta)`

Interoghează o singură dată. Nu consumă din cotă. Primește obiectul `VerificareInCurs` întors de `porneste()` sau un `ReferintaVerificare` construit de tine.

```php
use VerificRca\Cerere\OptiuniAsteptare;
use VerificRca\Cerere\ReferintaVerificare;

$r = $vrca->verificari->rezultat(new ReferintaVerificare(TipVerificare::Rca, 'B123ABC'));
if ($r instanceof VerificareInCurs) {
    $r = $vrca->verificari->asteapta($r, new OptiuniAsteptare(intervalMs: 5000, timeoutMs: 120000));
}
```

### `verificari->asteapta($referinta, ?OptiuniAsteptare $optiuni = null)`

Face polling până la rezultatul final. Opțiuni: `intervalMs` (implicit 3000, minim 2000) și `timeoutMs` (implicit 5 minute). La depășire aruncă `EroareTimeoutVerificare`. Reîncercările se opresc la termen, așa că doar o cerere HTTP deja pornită îl poate depăși, cu cel mult timeout-ul ei.

### `verificari->verificaSiAsteapta(CerereVerificare $cerere, ?OptiuniAsteptare $optiuni = null)`

`porneste()` urmat de `asteapta()`, într-un singur apel. E calea cea mai simplă pentru scripturi și joburi cron.

### `vehicule->lista()`

Toate vehiculele din cont, cu datele de expirare `rcaExpiraLa`, `itpExpiraLa`, `rovinietaExpiraLa` și `arrExpiraLa` (ca `DateTimeImmutable` sau `null`). Câmpul `activ` e `true` cât timp vehiculul e verificat automat și primește notificări de expirare. Vehiculele din cont sunt verificate automat și periodic, deci lista îți dă o imagine la zi a flotei fără să consume din cota de verificări.

```php
use VerificRca\Cerere\VehiculNou;

foreach ($vrca->vehicule->lista() as $v) {
    echo $v->numarInmatriculare, ' RCA: ', $v->rcaExpiraLa?->format('d.m.Y') ?? '-', PHP_EOL;
}

$v = $vrca->vehicule->adauga(new VehiculNou(numarInmatriculare: 'B123ABC', serieSasiu: 'WVWZZZ1JZXW000001'));
$vrca->vehicule->sterge($v->id);
```

### `vehicule->adauga(VehiculNou $vehicul)`

Adaugă un vehicul în monitorizare. Trimite cel puțin un identificator (`numarInmatriculare`, `serieSasiu`); opțional `arrMonitorizat`. Poți primi `EroareInterzis` cu `cod` `LIMIT_ATINS` când ai atins numărul de mașini din plan, sau `EroareConflict` când vehiculul există deja.

### `vehicule->sterge(string $id)`

Scoate vehiculul din cont. Aruncă `EroareNegasit` dacă ID-ul nu există în contul cheii.

Operațiile pe vehicule nu consumă din cota de verificări, dar cer un plan activ.

### Utilitare de validare

```php
use VerificRca\Validare;

Validare::esteNumarInmatriculareValid('CJ 45 XYZ'); // true
Validare::esteVinValid('WVWZZZ1JZXW00000I');        // false: litera I nu e permisă în VIN
Validare::normalizeaza(' b-123 abc ');              // 'B123ABC'
```

Le poți folosi pentru formularele tale, ca să validezi datele înainte să ajungă la server.

### Constante

| Constantă | Valoare |
|---|---|
| `Validare::TIPURI_VERIFICARE` | `['rca', 'itp', 'rovinieta', 'arr']`, util pentru dropdown-uri și validare |
| `Webhooks::HEADER_CHEIE` | `'x-verificrca-key'`, headerul cu cheia de webhook |
| `Webhooks::HEADER_EVENIMENT` | `'x-verificrca-event'`, headerul cu numele evenimentului |
| `VerificRca::URL_IMPLICIT` | `'https://www.verificrca.ro'` |
| `VerificRca::VERSIUNE_SDK` | versiunea pachetului, trimisă în `User-Agent` |

### Documentația tipurilor

Fiecare metodă, câmp și excepție are PHPDoc, cu parametri, valoare întoarsă și erorile posibile. IDE-ul (PhpStorm, VS Code cu Intelephense) îl arată la hover, iar PHPStan îl folosește la analiză.

## Webhook-uri

API-ul trimite două feluri de webhook. SDK-ul le validează strict și îți dă obiecte tipate, cu datele ca `DateTimeImmutable`.

### Rezultatul unei verificări (`callbackUrl`)

Când trimiți `callbackUrl` la `porneste()`, primești un POST cu rezultatul imediat ce e gata. Dacă sursa nu răspunde, primești `status` `StatusWebhook::Error`.

```php
use VerificRca\Enum\StatusWebhook;
use VerificRca\Webhooks;

// public/webhooks/verificrca-9f3k2.php
$r = Webhooks::parseazaWebhookRezultat(file_get_contents('php://input'));
if ($r->status !== StatusWebhook::Error) {
    salveaza($r->requestId, $r->result?->expiresAt);
}
http_response_code(200);
```

Pune un segment greu de ghicit în URL, păstrează-l pe HTTPS și verifică dacă `requestId`-ul primit corespunde celui întors de `porneste()` înainte să te bazezi pe conținut.

### Notificări de expirare (`document.expira`)

Pe planurile care includ webhook-uri, configurezi un URL în dashboard și primești câte un eveniment când un document al unui vehicul monitorizat intră într-un prag de expirare. Pentru RCA, ITP și rovinietă pragurile sunt 30, 14 și 3 zile, iar pentru ARR 60, 50 și 45 de zile. Fiecare cerere vine cu cheia contului în headerul `X-Verificrca-Key`. SDK-ul o compară în timp constant, ca să nu dezvăluie nimic prin durata comparației:

```php
use VerificRca\Exception\EroareWebhook;
use VerificRca\Model\WebhookDocumentExpira;
use VerificRca\Webhooks;

try {
    $ev = Webhooks::verificaWebhookNotificare(file_get_contents('php://input'), getallheaders(), (string) getenv('VERIFICRCA_WEBHOOK_KEY'));
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
use Illuminate\Http\Request;
use VerificRca\Exception\EroareWebhook;
use VerificRca\Model\WebhookDocumentExpira;
use VerificRca\Webhooks;

Route::post('/webhooks/verificrca', function (Request $request) {
    try {
        $ev = Webhooks::verificaWebhookNotificare($request->getContent(), $request->headers->all(), config('services.verificrca.webhook_key'));
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
use VerificRca\Model\VerificareInCurs;

$r = $vrca->verificari->porneste(new CerereVerificare(TipVerificare::Itp, 'WVWZZZ1JZXW000001'));
if ($r instanceof VerificareInCurs) {
    $salvat = json_encode($r); // în coadă, în baza de date
}

// mai târziu, în alt proces
$r = $vrca->verificari->rezultat(VerificareInCurs::fromArray(json_decode($salvat, true)));
```

## Erori

Toate excepțiile moștenesc `VerificRcaException`, care la rândul ei moștenește `RuntimeException`. Fiecare are `getMessage()` (în română), plus proprietățile `status`, `cod`, `hint` și `raspuns` când vin de la API.

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
| `EroareVerificare` | Sursa oficială nu a putut fi interogată | |
| `EroareTimeoutVerificare` | `asteapta()` a depășit `timeoutMs` | |
| `EroareWebhook` | Cheie greșită sau payload invalid | `cod` |

Toate sunt în namespace-ul `VerificRca\Exception`.

```php
use VerificRca\Exception\EroareLimita;
use VerificRca\Exception\EroareValidare;
use VerificRca\Exception\VerificRcaException;

try {
    $vrca->verificari->porneste(new CerereVerificare(TipVerificare::Rca, $numar));
} catch (EroareValidare $e) {
    return arataUtilizatorului($e->getMessage());
} catch (EroareLimita $e) {
    if ($e->cod === 'COTA_ATINSA') {
        return amanaPanaMaine();
    }
    throw $e;
} catch (VerificRcaException $e) {
    $logger->error($e->getMessage(), ['cod' => $e->cod, 'hint' => $e->hint]);
    throw $e;
}
```

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
$vrca = new VerificRca(
    timeoutMs: 15000,
    retry: ['incercari' => 5, 'intarziereInitialaMs' => 250, 'intarziereMaximaMs' => 8000],
);
```

## Cotă și limite

Fiecare `porneste()` consumă o unitate din cota zilnică și lunară a planului. Dacă mașina a fost verificată de curând, primești imediat rezultatul existent. Cererea tot consumă din cotă.

Rezultatele au proprietatea `limite`, completată din headerele `X-RateLimit-*`:

```php
$r = $vrca->verificari->porneste(new CerereVerificare(TipVerificare::Rca, 'B123ABC'));
echo $r->limite?->zi->ramase; // 287
echo $r->limite?->luna->limita; // 10000
```

Cota zilnică se resetează la miezul nopții. Limitele fiecărui plan le găsești pe [pagina API](https://www.verificrca.ro/api-acces).

## Exemple

Folderul [`examples/`](./examples) conține scripturi gata de rulat:

- [`verificare-rca.php`](./examples/verificare-rca.php): verificare RCA cu număr și VIN, tratarea erorilor;
- [`flota.php`](./examples/flota.php): raport cu RCA, ITP, rovinietă și ARR care expiră în 30 de zile, pentru toată flota;
- [`webhook-server.php`](./examples/webhook-server.php): primește ambele tipuri de webhook, pornit cu `php -S`.

### Verificare în masă

```php
foreach (['B123ABC', 'CJ45XYZ', 'IS08BCD'] as $numar) {
    try {
        $r = $vrca->verificari->verificaSiAsteapta(new CerereVerificare(TipVerificare::Rca, $numar));
        echo $numar, ': ', $r->status->value, PHP_EOL;
    } catch (VerificRcaException $e) {
        echo $numar, ': ', $e->getMessage(), PHP_EOL;
    }
}
```

Pentru liste mari, adaugă vehiculele în cont cu `vehicule->adauga()`. Sunt monitorizate automat și primești notificările de expirare, fără să consumi din cota de verificări.

## Întrebări frecvente

### Cum verific RCA-ul unei mașini din PHP?

Instalezi pachetul cu `composer require verificrca/sdk`, creezi un client cu cheia API și apelezi `$vrca->verificari->verificaSiAsteapta(new CerereVerificare(TipVerificare::Rca, 'B123ABC'))`. Primești statusul poliței și data expirării, din baza de date AIDA.

### Pot verifica ITP-ul după numărul de înmatriculare?

Nu. RAR caută inspecția tehnică periodică doar după seria de șasiu (VIN), așa că SDK-ul respinge cererea înainte să plece, fără să consume din cotă. VIN-ul îl găsești în talon, la rubrica E.

### De unde vin datele?

Din sursele oficiale: AIDA (baza de date a asigurărilor RCA administrată de BAAR), Registrul Auto Român pentru ITP, CNAIR pentru rovinietă și Autoritatea Rutieră Română pentru copia conformă. Nu estimăm nimic: fiecare rezultat e exact ce a răspuns sursa.

### De ce o poliță RCA nouă apare ca expirată?

Asigurătorul are nevoie de timp ca să înregistreze polița nouă în AIDA, de obicei între 1 și 7 zile. În acest interval verificarea poate arăta polița veche. Detalii în articolul despre [amenzile pentru RCA expirat](https://www.verificrca.ro/postari/amenzi-rca-expirat-2026).

### Merge cu Laravel, Symfony sau WordPress?

Da. E un pachet Composer fără dependențe de framework. În Laravel îl înregistrezi ca singleton în `AppServiceProvider`, iar în WordPress îl încarci din `vendor/autoload.php` al pluginului sau al temei. Cheia API rămâne pe server.

### Există SDK și pentru alte limbaje?

Da, pentru Node.js și TypeScript: [`@verificrca/sdk`](https://github.com/verificrca/sdk_javascript), cu aceleași metode și aceleași nume. Python urmează.

## Linkuri utile

- [Documentația API verificrca.ro](https://www.verificrca.ro/api-acces)
- [Verificare RCA online](https://www.verificrca.ro/verificare-rca)
- [Verificare ITP online](https://www.verificrca.ro/verificare-itp)
- [Verificare rovinietă online](https://www.verificrca.ro/verificare-rovinieta)
- [Monitorizare flote auto](https://www.verificrca.ro/flote)
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

Fără cheie rulează doar testul de autentificare. Cu `VERIFICRCA_API_KEY` se adaugă listarea vehiculelor, care e gratuită. Verificările reale (`VERIFICRCA_E2E_VERIFICARI=1`) consumă din cotă, iar testul de flotă (`VERIFICRCA_E2E_FLOTA=1`) adaugă și șterge un vehicul. Amândouă pornesc doar dacă le activezi explicit.

## Licență

[MIT](./LICENSE), © verificrca.ro
