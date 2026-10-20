<?php

declare(strict_types=1);

namespace AIC\Http\Domain;

/**
 * A resolved `Range` request over a resource of a known size: the kind, and
 * for a part its first and last byte, inclusive.
 */
final readonly class ByteRange
{
    /**
     * @param RangeKind $kind  Whole, part or unsatisfiable.
     * @param int       $first The first byte sent; 0 for the whole resource.
     * @param int       $last  The last byte sent, inclusive; `size - 1` for the whole resource.
     * @param int       $size  The resource's size, in bytes.
     */
    public function __construct(
        public RangeKind $kind,
        public int $first,
        public int $last,
        public int $size,
    ) {
    }

    /**
     * @return int How many bytes the answer carries.
     */
    public function length(): int
    {
        return $this->kind === RangeKind::Unsatisfiable
            ? 0
            : $this->last - $this->first + 1;
    }

    /**
     * @return string The `Content-Range` value: `bytes first-last/size`, or `bytes *\/size` when unsatisfiable.
     */
    public function contentRange(): string
    {
        return $this->kind === RangeKind::Unsatisfiable
            ? 'bytes */' . $this->size
            : 'bytes ' . $this->first . '-' . $this->last . '/' . $this->size;
    }
}
