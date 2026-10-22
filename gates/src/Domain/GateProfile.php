<?php

declare(strict_types=1);

namespace Gates\Domain;

/**
 * Which project a gate runs on (#130): an app built on AIC, or AIC's own
 * repository, which holds the framework, the workflow and the gates as
 * source. The tree decides it; no setting does.
 */
enum GateProfile: string
{
    case App = 'app';
    case Aic = 'aic';
}
