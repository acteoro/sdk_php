<?php

declare(strict_types=1);

namespace Acteo\Enum;

/**
 * Starea documentului:
 * - `Valid`: valabil si departe de expirare;
 * - `Warning`: expira curand (RCA 14 zile, ITP 30, rovinieta 7, ARR 14);
 * - `Expired`: expirat sau inexistent, confirmat de sursa oficiala;
 * - `NotFound`: sursa nu a gasit vehiculul.
 */
enum StatusDocument: string
{
    case Valid = 'valid';
    case Warning = 'warning';
    case Expired = 'expired';
    case NotFound = 'not_found';
}
