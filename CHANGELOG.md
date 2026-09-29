# Changelog

## 0.1.0 (nelansat)

Prima versiune.

- Client `VerificRca` cu `verificari->porneste`, `rezultat`, `asteapta` și `verificaSiAsteapta` pentru RCA, ITP, rovinietă și ARR.
- Gestionarea flotei: `vehicule->lista`, `adauga`, `sterge`.
- Webhook-uri: `Webhooks::parseazaWebhookRezultat` și `Webhooks::verificaWebhookNotificare` (cheie comparată în timp constant).
- Validare locală a numărului de înmatriculare și a VIN-ului, cu aceleași reguli ca API-ul.
- Excepții tipate, retry cu backoff exponențial și jitter, `Retry-After` respectat, niciun POST taxat repetat.
- Orice client HTTP PSR-18; Guzzle sau Symfony HttpClient folosite automat. PHP 8.1+.
