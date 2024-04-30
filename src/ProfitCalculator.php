<?php

require_once 'DataStructure/Transaction.php';
require_once 'DataStructure/TransactionSummary.php';
require_once 'Presenter.php';
require_once 'Importer.php';
require_once 'TransactionHandler.php';

try {
    $importer = new Importer();
    $bankTransactions = $importer->parseBankTransactions();

    $transactionHandler = new TransactionHandler();
    $summaries = $transactionHandler->getTransactionOverview($bankTransactions);

    $currentSharePrices = [
        'Evolution' => 1224.50,
        'Fast. Balder B' => 69.46,
        'British American Tobacco ADR' => 322.7629,
        'Philip Morris' => 1044.908,
        'Energy Fuels' => 60.2243
    ];

    $presenter = new Presenter();
    $presenter->presentResult($summaries, $currentSharePrices);
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage();
}
