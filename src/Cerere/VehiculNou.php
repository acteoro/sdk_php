<?php

declare(strict_types=1);

namespace Acteo\Cerere;

/** Datele unui vehicul adaugat in flota. Trimite cel putin numarul sau seria de sasiu. */
final class VehiculNou
{
    /**
     * @param string|null $numarInmatriculare spatiile si cratimele sunt eliminate; maxim 20 de caractere
     * @param string|null $serieSasiu necesara pentru ITP si rovinieta; maxim 50 de caractere
     * @param bool|null $arrMonitorizat monitorizeaza si copia conforma ARR; cere un plan activ
     * @param string|null $serieTalon numarul certificatului de inmatriculare (talon, ex: `I00639603F`);
     *   fara el rovinieta vehiculului nu se verifica
     */
    public function __construct(
        public readonly ?string $numarInmatriculare = null,
        public readonly ?string $serieSasiu = null,
        public readonly ?bool $arrMonitorizat = null,
        public readonly ?string $serieTalon = null,
    ) {}
}
