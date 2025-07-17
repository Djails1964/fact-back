<?php
// update_late_invoices.php
require_once __DIR__ . '/bootstrap.php';
require_once 'database.php';
require_once 'ServiceFacture.php';

// Créer l'instance du service
$serviceFacture = new ServiceFacture($conn);

// Mettre à jour les factures en retard
$resultat = $serviceFacture->mettreAJourFacturesEnRetard();

// Journaliser le résultat
file_put_contents(
    __DIR__ . '/logs/factures_retard_' . date('Y-m-d') . '.log',
    date('Y-m-d H:i:s') . ' - ' . json_encode($resultat) . PHP_EOL,
    FILE_APPEND
);

echo json_encode($resultat);