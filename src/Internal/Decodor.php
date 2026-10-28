<?php

declare(strict_types=1);

namespace Acteo\Internal;

/**
 * @internal Citire tipata din JSON-ul decodat. Campurile in plus sunt ignorate,
 * deci o versiune mai veche a SDK-ului nu se strica atunci cand API-ul creste.
 */
final class Decodor
{
    private const DATA_ISO = '/^\d{4}-\d{2}-\d{2}(T\d{2}:\d{2}(:\d{2}(\.\d{1,6})?)?(Z|[+-]\d{2}:?\d{2})?)?$/';

    private function __construct() {}

    public static function cale(string $prefix, string $cheie): string
    {
        return $prefix === '' ? $cheie : $prefix . '.' . $cheie;
    }

    /** @param array<mixed> $d */
    public static function sir(array $d, string $cheie, string $prefix): string
    {
        $v = self::obligatoriu($d, $cheie, $prefix);
        if (!is_string($v)) {
            throw self::gresit($prefix, $cheie, 'text', $v);
        }

        return $v;
    }

    /**
     * Cheie obligatorie, valoare text sau null.
     *
     * @param array<mixed> $d
     */
    public static function sirSauNull(array $d, string $cheie, string $prefix): ?string
    {
        $v = self::obligatoriu($d, $cheie, $prefix);

        return $v === null ? null : self::sir($d, $cheie, $prefix);
    }

    /**
     * Cheie optionala: lipsa sau null inseamna null.
     *
     * @param array<mixed> $d
     */
    public static function sirOptional(array $d, string $cheie, string $prefix): ?string
    {
        return ($d[$cheie] ?? null) === null ? null : self::sir($d, $cheie, $prefix);
    }

    /** @param array<mixed> $d */
    public static function data(array $d, string $cheie, string $prefix): \DateTimeImmutable
    {
        $v = self::obligatoriu($d, $cheie, $prefix);
        if (!is_string($v) || preg_match(self::DATA_ISO, $v) !== 1) {
            throw self::gresit($prefix, $cheie, 'data ISO 8601', $v);
        }
        try {
            return new \DateTimeImmutable($v, new \DateTimeZone('UTC'));
        } catch (\Exception) {
            throw self::gresit($prefix, $cheie, 'data ISO 8601', $v);
        }
    }

    /**
     * Cheie obligatorie, valoare data sau null.
     *
     * @param array<mixed> $d
     */
    public static function dataSauNull(array $d, string $cheie, string $prefix): ?\DateTimeImmutable
    {
        $v = self::obligatoriu($d, $cheie, $prefix);

        return $v === null ? null : self::data($d, $cheie, $prefix);
    }

    /**
     * Cheie optionala: lipsa sau null inseamna null.
     *
     * @param array<mixed> $d
     */
    public static function dataOptionala(array $d, string $cheie, string $prefix): ?\DateTimeImmutable
    {
        return ($d[$cheie] ?? null) === null ? null : self::data($d, $cheie, $prefix);
    }

    /** @param array<mixed> $d */
    public static function bool(array $d, string $cheie, string $prefix): bool
    {
        $v = self::obligatoriu($d, $cheie, $prefix);
        if (!is_bool($v)) {
            throw self::gresit($prefix, $cheie, 'boolean', $v);
        }

        return $v;
    }

    /** @param array<mixed> $d */
    public static function intreg(array $d, string $cheie, string $prefix): int
    {
        $v = self::obligatoriu($d, $cheie, $prefix);
        if (is_int($v)) {
            return $v;
        }
        if (is_float($v) && is_finite($v) && floor($v) === $v && abs($v) <= PHP_INT_MAX) {
            return (int) $v;
        }
        throw self::gresit($prefix, $cheie, 'numar intreg', $v);
    }

    /**
     * @param array<mixed> $d
     *
     * @return array<mixed>
     */
    public static function obiect(array $d, string $cheie, string $prefix): array
    {
        $v = self::obligatoriu($d, $cheie, $prefix);
        if (!is_array($v)) {
            throw self::gresit($prefix, $cheie, 'obiect', $v);
        }

        return $v;
    }

    /**
     * @param array<mixed> $d
     *
     * @return list<mixed>
     */
    public static function lista(array $d, string $cheie, string $prefix): array
    {
        $v = self::obligatoriu($d, $cheie, $prefix);
        if (!is_array($v) || !array_is_list($v)) {
            throw self::gresit($prefix, $cheie, 'lista', $v);
        }

        return $v;
    }

    /**
     * @template T of \BackedEnum
     *
     * @param array<mixed> $d
     * @param class-string<T> $enum
     *
     * @return T
     */
    public static function enum(array $d, string $cheie, string $prefix, string $enum): \BackedEnum
    {
        $v = self::obligatoriu($d, $cheie, $prefix);
        $caz = is_string($v) ? $enum::tryFrom($v) : null;
        if ($caz === null) {
            throw new FormaInvalida(self::cale($prefix, $cheie) . ': valoare necunoscuta ' . self::descrie($v));
        }

        return $caz;
    }

    /** @param array<mixed> $d */
    private static function obligatoriu(array $d, string $cheie, string $prefix): mixed
    {
        if (!array_key_exists($cheie, $d)) {
            throw new FormaInvalida(self::cale($prefix, $cheie) . ': lipseste');
        }

        return $d[$cheie];
    }

    private static function gresit(string $prefix, string $cheie, string $asteptat, mixed $v): FormaInvalida
    {
        return new FormaInvalida(self::cale($prefix, $cheie) . ": se astepta {$asteptat}, a venit " . self::descrie($v));
    }

    private static function descrie(mixed $v): string
    {
        if (is_scalar($v) || $v === null) {
            return (string) json_encode($v, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        return get_debug_type($v);
    }
}
