<?php

declare(strict_types=1);

namespace Acteo\Enum;

/**
 * Codurile stabile pe care API-ul le pune in `code`, in ordinea din documentatie.
 * Proprietatea `cod` a exceptiilor ramane `string`, ca un cod adaugat ulterior
 * de API sa nu strice nimic; compara cu `CodEroare::X->value` sau foloseste
 * `CodEroare::tryFrom($e->cod)`.
 *
 * Primele opt sunt cele din contract:
 * - `UPSTREAM_UNAVAILABLE`: sursa oficiala nu raspunde sau raspunde cu eroare;
 * - `UPSTREAM_TIMEOUT`: sursa oficiala nu a raspuns in intervalul maxim;
 * - `UPSTREAM_BLOCKED`: accesul la sursa oficiala e restrictionat temporar;
 * - `UPSTREAM_CHANGED`: structura sursei s-a modificat, serviciul e in adaptare;
 * - `DAILY_LIMIT_REACHED`: limita zilnica a planului, se reseteaza la miezul noptii (ora Romaniei);
 * - `PLAN_LIMIT_REACHED`: limita lunara, sau plafonul consumului peste plan;
 * - `NOT_FOUND`: identificatorul nu exista la sursa oficiala;
 * - `INVALID_IDENTIFIER`: sursa oficiala a respins identificatorul ca invalid.
 *
 * Restul tin de un produs anume: `SOLD_INSUFICIENT`, `PLAFON_NETAXAT`, `OCUPAT`,
 * `INDISPONIBIL`, `VIN_NEDECODAT`, `VIN_INVALID`, `ARR_NECESITA_PLAN`, `LIMIT_ATINS`
 * (numarul de masini din plan), `VEHICUL_EXISTENT`, `NEGASIT`, `LIMITA_FLOTA_ATINSA`.
 */
enum CodEroare: string
{
    case UpstreamUnavailable = 'UPSTREAM_UNAVAILABLE';
    case UpstreamTimeout = 'UPSTREAM_TIMEOUT';
    case UpstreamBlocked = 'UPSTREAM_BLOCKED';
    case UpstreamChanged = 'UPSTREAM_CHANGED';
    case DailyLimitReached = 'DAILY_LIMIT_REACHED';
    case PlanLimitReached = 'PLAN_LIMIT_REACHED';
    case NotFound = 'NOT_FOUND';
    case InvalidIdentifier = 'INVALID_IDENTIFIER';
    case SoldInsuficient = 'SOLD_INSUFICIENT';
    case PlafonNetaxat = 'PLAFON_NETAXAT';
    case Ocupat = 'OCUPAT';
    case Indisponibil = 'INDISPONIBIL';
    case VinNedecodat = 'VIN_NEDECODAT';
    case VinInvalid = 'VIN_INVALID';
    case ArrNecesitaPlan = 'ARR_NECESITA_PLAN';
    case LimitAtins = 'LIMIT_ATINS';
    case VehiculExistent = 'VEHICUL_EXISTENT';
    case Negasit = 'NEGASIT';
    case LimitaFlotaAtinsa = 'LIMITA_FLOTA_ATINSA';

    /**
     * De ce s-a incheiat o verificare cu statusul error (`EroareVerificare::$cod`):
     * - `INVALID_IDENTIFIER`: sursa a refuzat datele trimise; campul e in `codSursa`;
     * - `UPSTREAM_*`: sursa oficiala nu a dat un raspuns; reia mai tarziu cu o cerere noua.
     */
    public const ESEC_VERIFICARE = [
        self::InvalidIdentifier,
        self::UpstreamUnavailable,
        self::UpstreamTimeout,
        self::UpstreamBlocked,
        self::UpstreamChanged,
    ];

    /**
     * Toate codurile, ca text, in ordinea din documentatie.
     *
     * @return list<string>
     */
    public static function valori(): array
    {
        return array_map(static fn(self $c): string => $c->value, self::cases());
    }
}
