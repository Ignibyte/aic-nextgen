<?php

declare(strict_types=1);

namespace AIC\Schema\Contracts;

/**
 * How `indexUsing()` indexes its columns, past the plain ordered index that
 * `index()` makes (#310).
 */
enum IndexMethod
{
    /** Equality only, by hash. */
    case Hash;

    /** An inverted index: a JSON document's keys, an array's elements. */
    case Gin;

    /** A balanced tree over ranges, geometry or text search. */
    case Gist;

    /** Block ranges: small, for a column that grows with the table, such as a timestamp. */
    case Brin;

    /** Trigrams of text, for similarity and contains search; the migration names `extension('pg_trgm')`. */
    case Trigram;

    /** A JSON document's paths, for contains queries only; smaller than Gin. */
    case JsonPath;
}
