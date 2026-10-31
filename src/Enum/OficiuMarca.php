<?php

declare(strict_types=1);

namespace Acteo\Enum;

/** Oficiul unei marci: `Ro` = OSIM, `Em` = EUIPO (marca a Uniunii Europene). */
enum OficiuMarca: string
{
    case Ro = 'RO';
    case Em = 'EM';
}
