<?php

declare(strict_types=1);

namespace Acteo\Casa;

use Acteo\Internal\Ceas;
use Acteo\Internal\Http;

/** Domeniul casa: polita PAD a unei locuinte. Il accesezi prin `$acteo->casa`. */
final class ClientCasa
{
    /** Polita PAD: `$acteo->casa->pad->verifica(['cnp' => $cnp, 'judet' => 'Cluj', 'localitate' => 'Cluj-Napoca', 'acord' => true])`. */
    public readonly ClientPad $pad;

    /** @internal */
    public function __construct(Http $http, Ceas $ceas)
    {
        $this->pad = new ClientPad($http, $ceas);
    }
}
