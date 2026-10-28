<?php

declare(strict_types=1);

namespace Acteo\Internal;

/** @internal Ce difera intre clientul `Acteo` si cel legacy `VerificRca`. */
final class ProfilClient
{
    /**
     * @param string $urlImplicit adresa API-ului cand `baseUrl` lipseste
     * @param non-empty-list<string> $variabileCheie variabilele de mediu citite pentru cheie, in ordine
     * @param string $agent prefixul din `User-Agent`
     * @param string $undeCheie unde se genereaza cheia, aratat cand lipseste
     */
    public function __construct(
        public readonly string $urlImplicit,
        public readonly array $variabileCheie,
        public readonly string $agent,
        public readonly string $undeCheie,
    ) {}
}
