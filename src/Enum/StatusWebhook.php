<?php

declare(strict_types=1);

namespace VerificRca\Enum;

/** Starea din webhook-ul de rezultat: starea documentului sau `Error` cand sursa oficiala nu a raspuns. */
enum StatusWebhook: string
{
    case Valid = 'valid';
    case Warning = 'warning';
    case Expired = 'expired';
    case NotFound = 'not_found';
    case Error = 'error';
}
