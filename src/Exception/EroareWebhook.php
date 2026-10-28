<?php

declare(strict_types=1);

namespace Acteo\Exception;

/** Un webhook primit nu poate fi acceptat: cheie gresita sau payload invalid. */
final class EroareWebhook extends ActeoException {}
