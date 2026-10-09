# Changelog

## 0.2.0 (2026-10-09)

- Verificarea rovinietei cere `serieTalon`, numărul certificatului de înmatriculare tipărit jos pe talon (ex: `I00639603F`). SDK-ul îl normalizează (majuscule, doar litere și cifre) și îl validează local: lipsă sau invalid înseamnă `EroareValidare` cu `camp` `serieTalon`, fără cerere pe rețea. La celelalte verificări e ignorat.
- `EroareVerificare` are în `cod` motivul trimis de API când sursa a refuzat datele: `serie_talon_invalida`, `vin_invalid` sau `numar_invalid`. Fără motiv, `cod` rămâne `VERIFICARE_ESUATA`. Metoda nouă `dateRespinse()` spune dacă o reîncercare cu aceleași date are sens.
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
