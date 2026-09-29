<?php

declare(strict_types=1);

namespace VerificRca\Exception;

/** Sursa oficiala nu a putut fi interogata (statusul error). Verificarea poate fi reluata cu o cerere noua. */
final class EroareVerificare extends VerificRcaException {}
