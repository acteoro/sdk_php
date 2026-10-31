<?php

declare(strict_types=1);

namespace Acteo\Enum;

/** Campul schimbat la o firma monitorizata (`firma.schimbare`). */
enum CampFirma: string
{
    case Radiata = 'radiata';
    case Inactiv = 'inactiv';
    case PlatitorTva = 'platitor_tva';
    case TvaLaIncasare = 'tva_la_incasare';
    case SplitTva = 'split_tva';
    case EFactura = 'e_factura';
    case Denumire = 'denumire';
    case Adresa = 'adresa';
    case Caen = 'caen';
}
