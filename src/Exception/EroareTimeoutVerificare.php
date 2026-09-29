<?php

declare(strict_types=1);

namespace VerificRca\Exception;

/** asteapta() sau verificaSiAsteapta() a depasit timpul maxim de asteptare. */
final class EroareTimeoutVerificare extends VerificRcaException {}
