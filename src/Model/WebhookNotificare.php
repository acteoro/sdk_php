<?php

declare(strict_types=1);

namespace Acteo\Model;

/**
 * Orice notificare trimisa la URL-ul de webhook al contului. Toate au `event`,
 * `id` (unic per livrare, acelasi la reincercari) si `trimisLa`; distinge-le cu
 * `instanceof` sau dupa `event`.
 */
interface WebhookNotificare {}
