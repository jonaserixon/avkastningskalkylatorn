<?php

require_once 'DataStructure/Transaction.php';
require_once 'DataStructure/TransactionSummary.php';
require_once 'Enum/TransactionType.php';
require_once 'Enum/Bank.php';
require_once 'Presenter.php';
require_once 'Importer.php';
require_once 'Exporter.php';
require_once 'TransactionHandler.php';

$generateCsv = getenv('GENERATE_CSV') === 'yes' ? true : false;

try {
    $importer = new Importer();
    $bankTransactions = $importer->parseBankTransactions();

    $transactionHandler = new TransactionHandler();
    $summaries = $transactionHandler->getTransactionsOverview($bankTransactions);

    // Konverterat till SEK.
    $currentSharePrices = [
        'SE0020050417' => 356.50, // Boliden
    ];

    if ($generateCsv) {
        Exporter::generateCsvExport($summaries, $currentSharePrices);
    }

    $presenter = new Presenter();
    $presenter->presentResult($summaries, $currentSharePrices);
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage();
}

