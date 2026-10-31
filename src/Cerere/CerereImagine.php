<?php

declare(strict_types=1);

namespace Acteo\Cerere;

use Acteo\Enum\UnghiImagine;
use Acteo\Exception\EroareValidare;
use Acteo\Internal\Chei;

/** Masina pentru care ceri poza: `$acteo->auto->imagini->obtine(new CerereImagine('Volkswagen', 'Golf', 2015))`. */
final class CerereImagine
{
    /**
     * @param string $marca marca, ca in talon sau RAR, ex: `Volkswagen`
     * @param string $model modelul, ex: `Golf`
     * @param int $an anul fabricatiei (1950 - anul viitor); alege generatia
     * @param UnghiImagine|string|null $unghi unghiul pozei; implicit `trei-sferturi`
     * @param bool|null $umbra umbra sub masina; implicit `true`
     */
    public function __construct(
        public readonly string $marca,
        public readonly string $model,
        public readonly int $an,
        public readonly UnghiImagine|string|null $unghi = null,
        public readonly ?bool $umbra = null,
    ) {}

    /**
     * @internal
     *
     * @param array<mixed>|self $cerere array cu cheile `marca`, `model`, `an`, `unghi`, `umbra`
     *
     * @throws EroareValidare array cu chei necunoscute sau cu valori de alt tip
     */
    public static function din(array|self $cerere): self
    {
        if ($cerere instanceof self) {
            return $cerere;
        }
        Chei::verifica($cerere, ['marca', 'model', 'an', 'unghi', 'umbra']);
        $marca = $cerere['marca'] ?? '';
        $model = $cerere['model'] ?? '';
        $an = $cerere['an'] ?? null;
        $unghi = $cerere['unghi'] ?? null;
        $umbra = $cerere['umbra'] ?? null;
        if (!is_string($marca)) {
            throw self::eroare('Trimite `marca` ca text.', 'marca');
        }
        if (!is_string($model)) {
            throw self::eroare('Trimite `model` ca text.', 'model');
        }
        if (!is_int($an)) {
            throw self::eroare('`an` trebuie sa fie un numar intreg intre 1950 si ' . ((int) date('Y') + 1) . '.', 'an');
        }
        if ($unghi !== null && !is_string($unghi) && !$unghi instanceof UnghiImagine) {
            throw self::eroare('Trimite `unghi` ca text sau `UnghiImagine`.', 'unghi');
        }
        if ($umbra !== null && !is_bool($umbra)) {
            throw self::eroare('Trimite `umbra` ca true sau false.', 'umbra');
        }

        return new self($marca, $model, $an, $unghi, $umbra);
    }

    private static function eroare(string $mesaj, string $camp): EroareValidare
    {
        return new EroareValidare($mesaj, cod: 'VALIDARE_LOCALA', camp: $camp);
    }
}
