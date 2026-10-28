<?php

declare(strict_types=1);

namespace Acteo;

use Acteo\Cerere\CerereVerificare;
use Acteo\Cerere\VehiculNou;
use Acteo\Enum\TipVerificare;
use Acteo\Exception\EroareValidare;
use Acteo\Internal\TintaVerificare;

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
    private const SERIE_TALON_REGEX = '/^(?=.*\d)[A-Z0-9]{6,20}$/';
    private const MESAJ_SERIE_TALON_INVALIDA = '`serieTalon` invalid: se asteapta numarul certificatului de inmatriculare, '
        . 'tiparit jos pe talon (ex: I00639603F), adica 6-20 litere si cifre.';

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
     * Aduce numarul certificatului de inmatriculare (talon) la forma canonica:
     * doar litere si cifre, cu majuscule.
     *
     * Exemplu: `Validare::normalizeazaSerieTalon(' i 00639603-f ')` intoarce `'I00639603F'`.
     */
    public static function normalizeazaSerieTalon(string $valoare): string
    {
        return (string) preg_replace('/[^A-Z0-9]/', '', strtoupper($valoare));
    }

    /**
     * Verifica forma numarului certificatului de inmatriculare (talon), tiparit
     * jos pe talon (ex: `I00639603F`): 6-20 litere si cifre, cel putin o cifra,
     * dupa normalizare. Daca seria chiar apartine vehiculului afla doar sursa oficiala.
     */
    public static function esteSerieTalonValida(string $valoare): bool
    {
        return self::esteSerieTalon(self::normalizeazaSerieTalon($valoare));
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

        // Seria talonului se trimite doar la rovinieta, singura verificare care o cere.
        $serieTalon = $tip === TipVerificare::Rovinieta ? self::valideazaSerieTalonRovinieta($cerere->serieTalon) : null;

        return new TintaVerificare(
            $tip,
            $query,
            $serieSasiu,
            $queryPolling,
            self::valideazaCallbackUrl($cerere->callbackUrl),
            $serieTalon,
        );
    }

    /**
     * @internal Valideaza un vehicul nou cu regulile API-ului: cel putin un identificator.
     *
     * @return array{numarInmatriculare?: string, serieSasiu?: string, serieTalon?: string, arrMonitorizat?: bool}
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
        $serieTalon = self::normalizeazaSerieTalon($vehicul->serieTalon ?? '');
        if ($serieTalon !== '') {
            if (!self::esteSerieTalon($serieTalon)) {
                throw self::eroare(self::MESAJ_SERIE_TALON_INVALIDA, 'serieTalon');
            }
            $corp['serieTalon'] = $serieTalon;
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

    private static function valideazaSerieTalonRovinieta(?string $brut): string
    {
        $serie = self::normalizeazaSerieTalon($brut ?? '');
        if ($serie === '') {
            throw self::eroare(
                'Verificarea rovinietei cere si numarul certificatului de inmatriculare (talon) in `serieTalon`, de forma I00639603F.',
                'serieTalon',
            );
        }
        if (!self::esteSerieTalon($serie)) {
            throw self::eroare(self::MESAJ_SERIE_TALON_INVALIDA, 'serieTalon');
        }

        return $serie;
    }

    private static function valideazaCallbackUrl(?string $brut): ?string
    {
        if ($brut === null || trim($brut) === '') {
            return null;
        }
        $url = trim($brut);
        if (preg_match('//u', $url) !== 1) {
            throw self::eroare('`callbackUrl` trebuie sa fie text UTF-8 valid.', 'callbackUrl');
        }
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

    private static function esteSerieTalon(string $normalizat): bool
    {
        return preg_match(self::SERIE_TALON_REGEX, $normalizat) === 1;
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
