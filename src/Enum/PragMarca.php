<?php

declare(strict_types=1);

namespace Acteo\Enum;

/** Pragul de expirare al unei marci (`marca.expira`): 6, 3 sau o luna, 7 zile inainte; `Gratie` = a expirat, se mai poate reinnoi cu taxa. */
enum PragMarca: string
{
    case SaseLuni = '6l';
    case TreiLuni = '3l';
    case OLuna = '1l';
    case SapteZile = '7z';
    case Gratie = 'gratie';
}
