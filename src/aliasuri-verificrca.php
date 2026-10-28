<?php

declare(strict_types=1);

/*
 * Numele vechi `VerificRca\...` (pachetul verificrca/sdk) raman valabile, ca
 * alias pentru clasele `Acteo\...`. Aliasurile se definesc la incarcare, nu
 * lazy: `catch` si `instanceof` nu pornesc autoload-ul, deci un alias definit
 * abia la prima folosire nu ar prinde exceptiile.
 */

foreach ([
    'VerificRca' => 'VerificRca',
    'ClientVerificari' => 'ClientVerificari',
    'ClientVehicule' => 'ClientVehicule',
    'Validare' => 'Validare',
    'Webhooks' => 'Webhooks',
    'Cerere\CerereVerificare' => 'Cerere\CerereVerificare',
    'Cerere\OptiuniAsteptare' => 'Cerere\OptiuniAsteptare',
    'Cerere\ReferintaVerificare' => 'Cerere\ReferintaVerificare',
    'Cerere\VehiculNou' => 'Cerere\VehiculNou',
    'Enum\StatusDocument' => 'Enum\StatusDocument',
    'Enum\StatusWebhook' => 'Enum\StatusWebhook',
    'Enum\TipVerificare' => 'Enum\TipVerificare',
    'Exception\VerificRcaException' => 'Exception\ActeoException',
    'Exception\EroareAutentificare' => 'Exception\EroareAutentificare',
    'Exception\EroareConfigurare' => 'Exception\EroareConfigurare',
    'Exception\EroareConflict' => 'Exception\EroareConflict',
    'Exception\EroareHttp' => 'Exception\EroareHttp',
    'Exception\EroareInterzis' => 'Exception\EroareInterzis',
    'Exception\EroareLimita' => 'Exception\EroareLimita',
    'Exception\EroareNegasit' => 'Exception\EroareNegasit',
    'Exception\EroarePlanInactiv' => 'Exception\EroarePlanInactiv',
    'Exception\EroareRaspunsInvalid' => 'Exception\EroareRaspunsInvalid',
    'Exception\EroareRetea' => 'Exception\EroareRetea',
    'Exception\EroareServer' => 'Exception\EroareServer',
    'Exception\EroareTimeoutVerificare' => 'Exception\EroareTimeoutVerificare',
    'Exception\EroareValidare' => 'Exception\EroareValidare',
    'Exception\EroareVerificare' => 'Exception\EroareVerificare',
    'Exception\EroareWebhook' => 'Exception\EroareWebhook',
    'Internal\Ceas' => 'Internal\Ceas',
    'Internal\CeasSistem' => 'Internal\CeasSistem',
    'Model\Cota' => 'Model\Cota',
    'Model\Limite' => 'Model\Limite',
    'Model\Vehicul' => 'Model\Vehicul',
    'Model\VehiculWebhook' => 'Model\VehiculWebhook',
    'Model\VerificareFinala' => 'Model\VerificareFinala',
    'Model\VerificareInCurs' => 'Model\VerificareInCurs',
    'Model\WebhookDocumentExpira' => 'Model\WebhookDocumentExpira',
    'Model\WebhookRezultat' => 'Model\WebhookRezultat',
    'Model\WebhookTest' => 'Model\WebhookTest',
] as $vechi => $nou) {
    if (!class_exists('VerificRca\\' . $vechi, false) && !interface_exists('VerificRca\\' . $vechi, false)) {
        class_alias('Acteo\\' . $nou, 'VerificRca\\' . $vechi);
    }
}
