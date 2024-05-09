<?php

require_once 'DataStructure/Transaction.php';
require_once 'DataStructure/TransactionSummary.php';
require_once 'Enum/TransactionType.php';
require_once 'Enum/Bank.php';
require_once 'Presenter.php';
require_once 'Importer.php';
require_once 'TransactionHandler.php';

try {
    $importer = new Importer();
    $bankTransactions = $importer->parseBankTransactions();

    $transactionHandler = new TransactionHandler();
    $summaries = $transactionHandler->getTransactionsOverview($bankTransactions);

    // Konverterat till SEK.
    $currentSharePrices = [
        // 'Evolution' => 1235,
        // 'Fast. Balder B' => 70.34,
        // 'British American Tobacco ADR' => 329.0456,
        // 'Diageo ADR' => 1537.3281,
        // 'Philip Morris' => 1071.9075,
        // 'Energy Fuels' => 63.4962,
        // 'Uranium Royalty' => 27.3841,
        'SE0020050417' => 321.50, // Boliden
    ];

    $presenter = new Presenter();
    $presenter->presentResult($summaries, $currentSharePrices);
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage();
}
