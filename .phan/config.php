<?php

declare(strict_types=1);

// See more phan config options at: https://github.com/phan/phan/blob/v5/.phan/config.php and https://github.com/phan/phan/wiki/Incrementally-Strengthening-Analysis

$config = [
    'target_php_version' => null,
    'analyzed_file_extensions' => ['php'],
    'plugins' => [
        'AlwaysReturnPlugin',
        'DollarDollarPlugin',
        'UnreachableCodePlugin',
        'DuplicateArrayKeyPlugin',
        'PregRegexCheckerPlugin',
        'DuplicateExpressionPlugin',
        'EmptyStatementListPlugin',
        'LoopVariableReusePlugin',
        'PHPDocToRealTypesPlugin',
        'PHPDocRedundantPlugin',
        'PreferNamespaceUsePlugin',
        'RedundantAssignmentPlugin',
        'ShortArrayPlugin',
        'SimplifyExpressionPlugin',
        // 'StrictLiteralComparisonPlugin',
        'UnknownElementTypePlugin',
        // 'WhitespacePlugin',
        // 'PossiblyStaticMethodPlugin',
        'UseReturnValuePlugin',
    ],
    'directory_list' => [],
    'exclude_analysis_directory_list' => [],
    'exclude_file_list' => [],
    'enable_include_path_checks' => true,
    'suppress_issue_types' => [
        'PhanRedundantConditionInLoop',
        'PhanTypeMismatchArgumentNullable',
        'PhanTypeMismatchArgumentInternalProbablyReal',
        'PhanTypeMismatchDimFetchNullable',
        'PhanTypeMismatchArgumentNullableInternal',
        'PhanTypeMismatchDimAssignment',
        'PhanPluginUseReturnValueInternalKnown',
        'PhanPluginUnknownArrayClosureParamType',
    ],
    'whitelist_issue_types' => [],
    'force_tracking_references' => true,
    'redundant_condition_detection' => true,
    'simplify_ast' => true,
    'analyze_signature_compatibility' => true,
    'null_casts_as_any_type' => false,
    'null_casts_as_array' => false,
    'array_casts_as_null' => false,

    // 'dead_code_detection' => true,
    // 'error_prone_truthy_condition_detection' => true,
];

function getDirectoriesWithPhpFiles($rootDir, &$subdirs = [], $baseDir = '')
{
    $currentDir = $rootDir . '' . $baseDir;

    if (!is_dir($currentDir)) {
        return;
    }

    $contents = scandir($currentDir);
    $hasPhpFile = false;

    foreach ($contents as $item) {
        if ($item !== '.' && $item !== '..') {
            $itemPath = $currentDir . '/' . $item;
            if (is_dir($itemPath)) {
                getDirectoriesWithPhpFiles($rootDir, $subdirs, $baseDir . '/' . $item);
            } elseif (pathinfo($itemPath, PATHINFO_EXTENSION) === 'php') {
                $hasPhpFile = true;
            }
        }
    }

    if ($hasPhpFile && !in_array($currentDir, $subdirs)) {
        $subdirs[] = $currentDir;
    }
}

$directories = [
    'src' => ['exclude_analysis' => false],
];

foreach ($directories as $dirPath => $dirConfig) {
    $directoriesWithPhpFiles = [];
    getDirectoriesWithPhpFiles($dirPath, $directoriesWithPhpFiles);
    $config['directory_list'] = array_merge($config['directory_list'], $directoriesWithPhpFiles);

    if ($dirConfig['exclude_analysis']) {
        $config['exclude_analysis_directory_list'] = array_merge($config['exclude_analysis_directory_list'], $directoriesWithPhpFiles);
    }
}

return $config;
