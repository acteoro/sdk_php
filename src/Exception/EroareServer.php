<?php

declare(strict_types=1);

namespace VerificRca\Exception;

/** 5xx: eroare pe serverul verificrca. Cererile sigure se reincearca automat. */
final class EroareServer extends VerificRcaException {}
