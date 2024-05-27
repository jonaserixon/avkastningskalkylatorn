<?php

declare(strict_types=1);

// See more phan config options at: https://github.com/phan/phan/blob/v5/.phan/config.php and https://github.com/phan/phan/wiki/Incrementally-Strengthening-Analysis

/**
 * This configuration will be read and overlaid on top of the
 * default configuration. Command-line arguments will be applied
 * after this file is read.
 */
$config = [
    // Supported values: `'5.6'`, `'7.0'`, `'7.1'`, `'7.2'`, `'7.3'`,
    // `'7.4'`, `'8.0'`, `'8.1'`, `null`.
    // If this is set to `null`,
    // then Phan assumes the PHP version which is closest to the minor version
    // of the php executable used to execute Phan.
    //
    // Note that the **only** effect of choosing `'5.6'` is to infer
    // that functions removed in php 7.0 exist.
    // (See `backward_compatibility_checks` for additional options)
    'target_php_version' => null, // '8.0',
    'analyzed_file_extensions' => ['php'],

    // A list of directories that should be parsed for class and
    // method information. After excluding the directories
    // defined in exclude_analysis_directory_list, the remaining
    // files will be statically analyzed for errors.
    //
    // Thus, both first-party and third-party code being used by
    // your application should be included in this list.
    'directory_list' => [],

    // A list of plugin files to execute.
    // Plugins which are bundled with Phan can be added here by providing their name
    // (e.g. 'AlwaysReturnPlugin')
    //
    // Documentation about available bundled plugins can be found
    // at https://github.com/phan/phan/tree/v5/.phan/plugins
    //
    // Alternately, you can pass in the full path to a PHP file
    // with the plugin's implementation.
    // (e.g. 'vendor/phan/phan/.phan/plugins/AlwaysReturnPlugin.php')
    'plugins' => [
        // 'AlwaysReturnPlugin',
        'DollarDollarPlugin',
        'UnreachableCodePlugin',
        'DuplicateArrayKeyPlugin',
        'PregRegexCheckerPlugin',
        // 'UseReturnValuePlugin',
        // 'UnknownElementTypePlugin',
        // 'DuplicateExpressionPlugin',
        // 'WhitespacePlugin',
        'EmptyStatementListPlugin',
        'LoopVariableReusePlugin',
    ],
    // A directory list that defines files that will be excluded
    // from static analysis, but whose class and method
    // information should be included.
    //
    // Generally, you'll want to include the directories for
    // third-party code (such as "vendor/") in this list.
    //
    // n.b.: If you'd like to parse but not analyze 3rd
    //       party code, directories containing that code
    //       should be added to both the `directory_list`
    //       and `exclude_analysis_directory_list` arrays.
    'exclude_analysis_directory_list' => [],
    'exclude_file_list' => [],
    'enable_include_path_checks' => true,

    'suppress_issue_types' => [
        'PhanTypeArraySuspiciousNullable',
        'PhanTypePossiblyInvalidDimOffset',
        'PhanPossiblyNullTypeMismatchProperty',
        'PhanPossiblyUndeclaredVariable',
        'PhanTypeInvalidDimOffset',

        'PhanTypeSuspiciousStringExpression',
        'PhanTypeMismatchDimFetch',

        'PhanSuspiciousTruthyString',
        'PhanSuspiciousTruthyCondition',
        'PhanSuspiciousValueComparison',
        'PhanCoalescingNeverNull',
        'PhanTypeComparisonFromArray',

        'PhanTypeSuspiciousNonTraversableForeach', // TODO: enable this in the future.
    ],
    'whitelist_issue_types' => [],

    'null_casts_as_any_type' => true, // TODO: set as false if we want stricter checks
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
