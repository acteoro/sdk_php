<?php

declare(strict_types=1);

namespace Acteo\Exception;

/** 5xx: eroare pe serverul API-ului. Cererile sigure se reincearca automat. */
final class EroareServer extends ActeoException {}
