<?php

declare(strict_types=1);

namespace Acteo\Exception;

/** Asteptarea unei verificari (`verifica()`, `asteapta()`) sau a unei poze (`imagini->asteapta()`) a depasit timpul maxim. */
final class EroareTimeoutVerificare extends ActeoException {}
