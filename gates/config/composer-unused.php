<?php

declare(strict_types=1);

use ComposerUnused\ComposerUnused\Configuration\Configuration;

/*
 * gate:4: every required package must be used. No filter: composer-unused fails
 * on an exclusion that matches nothing, so an `ext-*` filter returns with the
 * first `ext-*` require.
 */
return static fn (Configuration $configuration): Configuration => $configuration;
