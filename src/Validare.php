<?php

declare(strict_types=1);

namespace VerificRca;

use VerificRca\Cerere\CerereVerificare;
use VerificRca\Cerere\VehiculNou;
use VerificRca\Enum\TipVerificare;
use VerificRca\Exception\EroareValidare;
use VerificRca\Internal\TintaVerificare;

/**
 * Validarea locala a numerelor de inmatriculare si a VIN-urilor, cu aceleasi
 * reguli ca API-ul. O cerere pe care sursa oficiala ar refuza-o oricum e oprita
 * aici: nu pleaca pe retea si nu consuma din cota.
 */
final class Validare
{
    /** Toate tipurile de verificare acceptate de API, in ordine. */
    public const TIPURI_VERIFICARE = ['rca', 'itp', 'rovinieta', 'arr'];

    private const JUDETE = 'AB|AR|AG|BC|BH|BN|BT|BV|BR|BZ|CS|CL|CJ|CT|CV|DB|DJ|GL|GR|GJ|HD|HR|IL|IS|IF|MM|MH|MS|NT|OT|PH|SM|SJ|SB|SV|TR|TM|TL|VS|VL|VN';
    private const NUMAR_REGEX = '/^(B\d{2,3}[A-Z]{2,3}|(' . self::JUDETE . ')\d{2}[A-Z]{2,3}|CD\d{3}[A-Z]{1,3}|TC\d{4}[A-Z]{2})$/i';
    private const VIN_REGEX = '/^[A-HJ-NPR-Z0-9]{17}$/i';
    private const LUNGIME_VIN = 17;

    private function __construct() {}

    /**
     * Aduce un numar de inmatriculare sau un VIN la forma canonica: fara spatii,
     * cratime sau underscore, cu majuscule.
     *
     * Exemplu: `Validare::normalizeaza(' b-123 abc ')` intoarce `'B123ABC'`.
     */
    public static function normalizeaza(string $valoare): string
    {
        return (string) preg_replace('/[\s\-_]/', '', strtoupper(trim($valoare)));
    }

    /**
     * Verifica un numar de inmatriculare romanesc: Bucuresti (`B123ABC`), judete
     * (`CJ45XYZ`), corp diplomatic (`CD123A`) si numere temporare (`TC1234AB`).
     * Accepta si forme cu spatii sau cratime.
     */
    public static function esteNumarInmatriculareValid(string $valoare): bool
    {
        return self::esteNumar(self::normalizeaza($valoare));
    }

    /**
     * Verifica o serie de sasiu (VIN) dupa ISO 3779: exact 17 caractere, cifre si
     * litere, fara I, O si Q. Spatiile si cratimele sunt ignorate.
     */
    public static function esteVinValid(string $valoare): bool
    {
        return self::esteVin(self::normalizeaza($valoare));
    }

    /**
     * @internal Valideaza si normalizeaza o cerere de verificare.
     *
     * @throws EroareValidare
     */
    public static function valideazaCerereVerificare(CerereVerificare $cerere): TintaVerificare
    {
        $tip = $cerere->tip instanceof TipVerificare
            ? $cerere->tip
            : TipVerificare::tryFrom(strtolower(trim($cerere->tip)));
        if ($tip === null) {
            throw self::eroare('`tip` invalid.', 'tip', self::TIPURI_VERIFICARE);
        }

        [$query, $serieSasiu, $queryPolling] = self::valideazaIdentificatori(
            $tip,
            self::normalizeaza($cerere->query),
            self::normalizeaza($cerere->serieSasiu ?? ''),
        );

        return new TintaVerificare($tip, $query, $serieSasiu, $queryPolling, self::valideazaCallbackUrl($cerere->callbackUrl));
    }

    /**
     * @internal Valideaza un vehicul nou cu regulile API-ului: cel putin un identificator.
     *
     * @return array{numarInmatriculare?: string, serieSasiu?: string, arrMonitorizat?: bool}
     *
     * @throws EroareValidare
     */
    public static function valideazaVehiculNou(VehiculNou $vehicul): array
    {
        $numar = trim($vehicul->numarInmatriculare ?? '');
        $serie = trim($vehicul->serieSasiu ?? '');

        if ($numar !== '' && (strlen($numar) > 20 || preg_match('/^[A-Z0-9\s-]*$/i', $numar) !== 1)) {
            throw self::eroare('`numarInmatriculare` poate contine doar litere, cifre, spatii si cratime (maxim 20).', 'numarInmatriculare');
        }
        if ($serie !== '' && (strlen($serie) > 50 || preg_match('/^[A-Z0-9]*$/i', $serie) !== 1)) {
            throw self::eroare('`serieSasiu` poate contine doar litere si cifre (maxim 50).', 'serieSasiu');
        }

        $numarNormalizat = (string) preg_replace('/[\s-]+/', '', strtoupper($numar));
        $serieNormalizata = strtoupper($serie);
        if ($numarNormalizat === '' && $serieNormalizata === '') {
            throw self::eroare('Trimite numarul de inmatriculare sau seria de sasiu.', 'numarInmatriculare');
        }

        $corp = [];
        if ($numarNormalizat !== '') {
            $corp['numarInmatriculare'] = $numarNormalizat;
        }
        if ($serieNormalizata !== '') {
            $corp['serieSasiu'] = $serieNormalizata;
        }
        if ($vehicul->arrMonitorizat !== null) {
            $corp['arrMonitorizat'] = $vehicul->arrMonitorizat;
        }

        return $corp;
    }

    /**
     * @return array{0: string, 1: string|null, 2: string} query, serieSasiu, queryPolling
     */
    private static function valideazaIdentificatori(TipVerificare $tip, string $query, string $serie): array
    {
        if ($query === '') {
            throw self::eroare('Lipseste `query` (numar de inmatriculare sau serie de sasiu).', 'query');
        }

        switch ($tip) {
            case TipVerificare::Rca:
                if (self::esteNumar($query)) {
                    if ($serie === '') {
                        return [$query, null, $query];
                    }
                    if (!self::esteVin($serie)) {
                        throw self::eroare('`serieSasiu` invalid. ' . self::explicaVin($serie), 'serieSasiu');
                    }

                    // Cu VIN, API-ul cauta polita dupa VIN, deci si pollingul merge pe VIN.
                    return [$query, $serie, $serie];
                }
                if (self::esteVin($query)) {
                    return [$query, null, $query];
                }
                throw self::eroare(
                    '`query` invalid: se accepta un numar de inmatriculare romanesc (ex: B123ABC) sau o serie de sasiu de '
                    . self::LUNGIME_VIN . ' caractere. ' . self::explicaVin($query),
                    'query',
                );

            case TipVerificare::Itp:
                $vin = self::esteVin($query) ? $query : (self::esteVin($serie) ? $serie : null);
                if ($vin === null) {
                    throw self::eroare(
                        'Verificarea ITP cere seria de sasiu (VIN, ' . self::LUNGIME_VIN
                        . ' caractere). RAR nu cauta dupa numarul de inmatriculare. '
                        . self::explicaVin($serie !== '' ? $serie : $query),
                        'query',
                    );
                }

                return [$query, $serie !== '' ? $serie : null, $vin];

            case TipVerificare::Arr:
                if (!self::esteNumar($query)) {
                    throw self::eroare('Verificarea ARR cere numarul de inmatriculare in `query` (ex: B123ABC). ARR nu cauta dupa VIN.', 'query');
                }

                return [$query, null, $query];

            case TipVerificare::Rovinieta:
                if (!self::esteNumar($query)) {
                    throw self::eroare('Verificarea rovinietei cere numarul de inmatriculare in `query` (ex: B123ABC).', 'query');
                }
                if (!self::esteVin($serie)) {
                    throw self::eroare('Verificarea rovinietei cere si seria de sasiu in `serieSasiu`. ' . self::explicaVin($serie), 'serieSasiu');
                }

                return [$query, $serie, $query];
        }
    }

    private static function valideazaCallbackUrl(?string $brut): ?string
    {
        if ($brut === null || trim($brut) === '') {
            return null;
        }
        $url = trim($brut);
        if (strlen($url) > 2000) {
            throw self::eroare('`callbackUrl` e prea lung (maxim 2000 de caractere).', 'callbackUrl');
        }

        $parti = parse_url($url);
        $schema = is_array($parti) && isset($parti['scheme']) ? strtolower($parti['scheme']) : null;
        if ($schema !== null && $schema !== 'http' && $schema !== 'https') {
            throw self::eroare('`callbackUrl` trebuie sa foloseasca http sau https.', 'callbackUrl');
        }
        if ($schema === null || ($parti['host'] ?? '') === '') {
            throw self::eroare('`callbackUrl` trebuie sa fie un URL absolut http(s).', 'callbackUrl');
        }

        return $url;
    }

    private static function explicaVin(string $valoare): string
    {
        if ($valoare === '') {
            return 'Lipseste seria de sasiu (VIN).';
        }
        if (strlen($valoare) !== self::LUNGIME_VIN) {
            return 'Seria de sasiu are ' . strlen($valoare) . ' caractere, sunt necesare exact ' . self::LUNGIME_VIN . '.';
        }

        return 'Seria de sasiu contine caractere neacceptate. Standardul ISO 3779 exclude literele I, O si Q.';
    }

    private static function esteNumar(string $normalizat): bool
    {
        return preg_match(self::NUMAR_REGEX, $normalizat) === 1;
    }

    private static function esteVin(string $normalizat): bool
    {
        return preg_match(self::VIN_REGEX, $normalizat) === 1;
    }

    /** @param list<string>|null $acceptate */
    private static function eroare(string $mesaj, string $camp, ?array $acceptate = null): EroareValidare
    {
        return new EroareValidare($mesaj, cod: 'VALIDARE_LOCALA', acceptate: $acceptate, camp: $camp);
    }
}
