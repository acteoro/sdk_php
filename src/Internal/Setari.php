<?php

declare(strict_types=1);

namespace Acteo\Internal;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

/** @internal Configuratia validata a clientului. */
final class Setari
{
    /**
     * @param int $timeoutMs timeout-ul cu care a fost construit `$http`
     * @param (\Closure(int): ClientInterface)|null $clientCuTimeout construieste un client cu alt
     *   timeout; null cand clientul e injectat si isi pastreaza timeout-ul propriu
     */
    public function __construct(
        public readonly string $apiKey,
        public readonly string $baseUrl,
        public readonly ?SetariRetry $retry,
        public readonly ClientInterface $http,
        public readonly RequestFactoryInterface $cereri,
        public readonly StreamFactoryInterface $fluxuri,
        public readonly string $userAgent,
        public readonly Ceas $ceas,
        public readonly ?string $urlImplicit = null,
        public readonly int $timeoutMs = 30000,
        public readonly ?\Closure $clientCuTimeout = null,
    ) {}
}
