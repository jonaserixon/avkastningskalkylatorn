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

    // A list of directories that should be parsed for class and
    // method information. After excluding the directories
    // defined in exclude_analysis_directory_list, the remaining
    // files will be statically analyzed for errors.
    //
    // Thus, both first-party and third-party code being used by
    // your application should be included in this list.
    'directory_list' => [

    ],

    // If we add stubs here we also need to include the stubs directory under 'directory_list' above.
    'autoload_internal_extension_signatures' => [

    ],

    // TODO: decrease level as we fix issues
    // 'minimum_severity' => 5, // 10 = critical, 5 = normal, 0 = low

    // A regex used to match every file name that you want to
    // exclude from parsing. Actual value will exclude every
    // "test", "tests", "Test" and "Tests" folders found in
    // "vendor/" directory.
    'exclude_file_regex' => '@^(vendor/.*/(tests?|Tests?)/|[^.]+(?<!\.php))$@', // exclude all non-PHP files and all test files

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
    'exclude_analysis_directory_list' => [
        
    ],

    'exclude_file_list' => [
        
    ],

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
        'PhanTypeComparisonFromArray'
    ],

    'whitelist_issue_types' => [],

    'analyzed_file_extensions' => ['php'],

    'dead_code_detection' => false,

    // Set to true in order to attempt to detect unused variables.
    // `dead_code_detection` will also enable unused variable detection.
    //
    // This has a few known false positives, e.g. for loops or branches.
    'unused_variable_detection' => false,

    // Set to true in order to force tracking references to elements
    // (functions/methods/consts/protected).
    // dead_code_detection is another option which also causes references
    // to be tracked.
    'force_tracking_references' => false,

    // Set to true in order to attempt to detect redundant and impossible conditions.
    //
    // This has some false positives involving loops,
    // variables set in branches of loops, and global variables.
    'redundant_condition_detection' => true,

    // Set to true in order to attempt to detect error-prone truthiness/falsiness checks.
    //
    // This is not suitable for all codebases.
    'error_prone_truthy_condition_detection' => true,

    // Enable this to warn about harmless redundant use for classes and namespaces such as `use Foo\bar` in namespace Foo.
    //
    // Note: This does not affect warnings about redundant uses in the global namespace.
    'warn_about_redundant_use_namespaced_class' => false,

    // If true, then run a quick version of checks that takes less time.
    // False by default.
    // 'quick_mode' => false,

    // If true, then before analysis, try to simplify AST into a form
    // which improves Phan's type inference in edge cases.
    //
    // This may conflict with 'dead_code_detection'.
    // When this is true, this slows down analysis slightly.
    //
    // E.g. rewrites `if ($a = value() && $a > 0) {...}`
    // into $a = value(); if ($a) { if ($a > 0) {...}}`
    'simplify_ast' => true,

    // If true, Phan will read `class_alias` calls in the global scope,
    // then (1) create aliases from the *parsed* files if no class definition was found,
    // and (2) emit issues in the global scope if the source or target class is invalid.
    // (If there are multiple possible valid original classes for an aliased class name,
    //  the one which will be created is unspecified.)
    // NOTE: THIS IS EXPERIMENTAL, and the implementation may change.
    'enable_class_alias_support' => false,

    // Enable or disable support for generic templated
    // class types.
    'generic_types_enabled' => true,

    // If enabled, warn about throw statement where the exception types
    // are not documented in the PHPDoc of functions, methods, and closures.
    'warn_about_undocumented_throw_statements' => false,

    // If enabled (and warn_about_undocumented_throw_statements is enabled),
    // warn about function/closure/method calls that have (at)throws
    // without the invoking method documenting that exception.
    'warn_about_undocumented_exceptions_thrown_by_invoked_functions' => false,

    // If enabled, check all methods that override a
    // parent method to make sure its signature is
    // compatible with the parent's. This check
    // can add quite a bit of time to the analysis.
    'analyze_signature_compatibility' => true,

    // Allow null to be cast as any type and for any
    // type to be cast to null.
    'null_casts_as_any_type' => true, // TODO: set as 'false' if we want stricter checks
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
    'App' => ['exclude_analysis' => false],
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
