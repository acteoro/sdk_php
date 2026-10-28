<?php

declare(strict_types=1);

namespace Acteo\Tests\Ajutor;

use GuzzleHttp\Psr7\Response;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/** Client PSR-18 care raspunde pe rand cu valorile date si inregistreaza cererile. */
final class ClientFals implements ClientInterface
{
    /** @var list<ResponseInterface|\Throwable> */
    private array $coada;

    /** @var list<RequestInterface> */
    public array $cereri = [];

    public function __construct(ResponseInterface|\Throwable ...$raspunsuri)
    {
        $this->coada = array_values($raspunsuri);
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $this->cereri[] = $request;
        $urmator = array_shift($this->coada);
        if ($urmator === null) {
            throw new \LogicException('ClientFals: nu mai sunt raspunsuri');
        }
        if ($urmator instanceof \Throwable) {
            throw $urmator;
        }

        return $urmator;
    }

    /** @param array<string, string> $headere */
    public static function raspuns(int $status = 200, mixed $corp = null, array $headere = []): Response
    {
        $text = match (true) {
            $corp === null => '',
            is_string($corp) => $corp,
            default => json_encode($corp, JSON_THROW_ON_ERROR),
        };

        return new Response($status, $headere, $text);
    }

    public function cerere(int $index = 0): RequestInterface
    {
        return $this->cereri[$index] ?? throw new \LogicException("nu exista cererea {$index}");
    }

    /** @return array<mixed> */
    public function corp(int $index = 0): array
    {
        $corp = json_decode((string) $this->cerere($index)->getBody(), true, 512, JSON_THROW_ON_ERROR);

        return is_array($corp) ? $corp : throw new \LogicException('corpul nu e obiect');
    }

    /** @return array<mixed> */
    public function query(int $index = 0): array
    {
        parse_str($this->cerere($index)->getUri()->getQuery(), $parametri);

        return $parametri;
    }
}
