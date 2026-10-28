<?php

declare(strict_types=1);

namespace Acteo;

use Acteo\Cerere\VehiculNou;
use Acteo\Exception\EroareValidare;
use Acteo\Internal\Decodor;
use Acteo\Internal\FormaInvalida;
use Acteo\Internal\Http;
use Acteo\Model\Vehicul;

/**
 * Vehiculele monitorizate din cont. Il accesezi prin `$acteo->auto->flota`, nu il
 * construiesti direct.
 *
 * Operatiile sunt gratuite (nu consuma din cota de verificari), dar cer un plan
 * activ: abonament API sau plan de cont. Vehiculele din cont sunt verificate
 * automat si periodic, iar la apropierea expirarilor primesti notificari.
 */
final class ClientVehicule
{
    private const CALE = '/api/public/v1/vehicule';

    /** @internal */
    public function __construct(private readonly Http $http) {}

    /**
     * Listeaza toate vehiculele din cont, cu datele de expirare RCA, ITP,
     * rovinieta si ARR.
     *
     * @return list<Vehicul> lista goala daca nu ai niciun vehicul
     *
     * @throws Exception\EroareAutentificare cheie API invalida (401)
     * @throws Exception\EroarePlanInactiv contul nu are un plan activ (402)
     * @throws Exception\EroareRetea fara raspuns, dupa reincercari
     */
    public function lista(): array
    {
        $raspuns = $this->http->trimite('GET', self::CALE, true);

        return Http::decodeaza($raspuns, static function (array $d): array {
            $vehicule = [];
            foreach (Decodor::lista($d, 'vehicule', '') as $i => $v) {
                if (!is_array($v)) {
                    throw new FormaInvalida("vehicule.{$i}: se astepta obiect");
                }
                $vehicule[] = Vehicul::fromArray($v, "vehicule.{$i}");
            }

            return $vehicule;
        });
    }

    /**
     * Adauga un vehicul in monitorizare. Trimite cel putin numarul de
     * inmatriculare sau seria de sasiu; cu amandoua se pot urmari toate
     * documentele (ITP-ul si rovinieta cer VIN).
     *
     * @return Vehicul vehiculul creat, cu `id`-ul folosit la stergere
     *
     * @throws EroareValidare lipseste identificatorul sau are caractere nepermise
     * @throws Exception\EroareInterzis LIMIT_ATINS (vezi `limita`) sau ARR_NECESITA_PLAN
     * @throws Exception\EroareConflict vehiculul exista deja in cont (VEHICUL_EXISTENT)
     * @throws Exception\EroareLimita plafonul zilnic de adaugari prin API (LIMITA_FLOTA_ATINSA)
     * @throws Exception\EroarePlanInactiv contul nu are un plan activ (402)
     */
    public function adauga(VehiculNou $vehicul): Vehicul
    {
        $raspuns = $this->http->trimite('POST', self::CALE, false, [], Validare::valideazaVehiculNou($vehicul));

        return Http::decodeaza(
            $raspuns,
            static fn(array $d): Vehicul => Vehicul::fromArray(Decodor::obiect($d, 'vehicul', ''), 'vehicul'),
        );
    }

    /**
     * Scoate un vehicul din cont. Monitorizarea si notificarile lui se opresc.
     *
     * @param string $id `id`-ul vehiculului, din `lista()` sau `adauga()`
     *
     * @throws Exception\EroareNegasit vehiculul nu exista in contul cheii (404)
     * @throws Exception\EroareLimita plafonul zilnic de stergeri prin API (LIMITA_FLOTA_ATINSA)
     * @throws EroareValidare `id` gol
     */
    public function sterge(string $id): void
    {
        $id = trim($id);
        if ($id === '') {
            throw new EroareValidare('Lipseste ID-ul vehiculului.', cod: 'VALIDARE_LOCALA', camp: 'id');
        }
        $raspuns = $this->http->trimite('DELETE', self::CALE . '/' . rawurlencode($id), true);

        Http::decodeaza($raspuns, static function (array $d): bool {
            if (($d['success'] ?? null) !== true) {
                throw new FormaInvalida('success: se astepta true');
            }

            return true;
        });
    }
}
