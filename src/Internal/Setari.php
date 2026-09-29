<?php

declare(strict_types=1);

namespace VerificRca\Internal;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

/** @internal Configuratia validata a clientului. */
final class Setari
{
    public function __construct(
        public readonly string $apiKey,
        public readonly string $baseUrl,
        public readonly ?SetariRetry $retry,
        public readonly ClientInterface $http,
        public readonly RequestFactoryInterface $cereri,
        public readonly StreamFactoryInterface $fluxuri,
        public readonly string $userAgent,
        public readonly Ceas $ceas,
    ) {}
}
