<?php

declare(strict_types=1);

namespace Gates\Domain;

/** Explicit rendering roles distinguish marked components from transparent document and page wrappers. */
enum TemplateRole
{
    case Component;
    case Atom;
    case Html;
    case Page;
}
