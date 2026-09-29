<?php

declare(strict_types=1);

namespace VerificRca\Enum;

/** Tipurile de verificare: RCA (AIDA/BAAR), ITP (RAR), rovinieta (CNAIR), ARR (copie conforma). */
enum TipVerificare: string
{
    case Rca = 'rca';
    case Itp = 'itp';
    case Rovinieta = 'rovinieta';
    case Arr = 'arr';
}
