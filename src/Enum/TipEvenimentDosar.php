<?php

declare(strict_types=1);

namespace Acteo\Enum;

/**
 * Ce s-a intamplat intr-un dosar monitorizat (`dosar.eveniment`):
 * - `TermenNou`: sedinta noua;
 * - `Solutie`: solutie pronuntata;
 * - `CaleAtac`: apel, recurs;
 * - `Stadiu`: stadiu procesual nou;
 * - `InstantaNoua`: dosarul a ajuns la alta instanta;
 * - `IncheiatAuto`: 180 de zile fara noutati, dosarul nu mai e monitorizat.
 */
enum TipEvenimentDosar: string
{
    case TermenNou = 'termen_nou';
    case Solutie = 'solutie';
    case CaleAtac = 'cale_atac';
    case Stadiu = 'stadiu';
    case InstantaNoua = 'instanta_noua';
    case IncheiatAuto = 'incheiat_auto';
}
