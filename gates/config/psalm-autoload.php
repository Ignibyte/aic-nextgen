<?php

declare(strict_types=1);

use Composer\InstalledVersions;

// PHPCS owns its class loader and global token declarations; load the installed sources.
$phpcsPath = InstalledVersions::getInstallPath('squizlabs/php_codesniffer');

if ($phpcsPath === null) {
    throw new RuntimeException('Psalm requires the installed PHP_CodeSniffer package.');
}

$phpcsAutoload = $phpcsPath . '/autoload.php';
$phpcsTokens = $phpcsPath . '/src/Util/Tokens.php';

if (!is_file($phpcsAutoload)) {
    throw new RuntimeException('Psalm cannot load PHP_CodeSniffer declarations from ' . $phpcsPath . '.');
}

require_once $phpcsAutoload;

if (!is_file($phpcsTokens)) {
    throw new RuntimeException('Psalm cannot load PHP_CodeSniffer tokens from ' . $phpcsPath . '.');
}

require_once $phpcsTokens;
