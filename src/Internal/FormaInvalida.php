<?php

declare(strict_types=1);

namespace VerificRca\Internal;

/** @internal Un camp lipseste sau are alt tip. Apelantul o transforma in eroarea publica potrivita. */
final class FormaInvalida extends \RuntimeException {}
