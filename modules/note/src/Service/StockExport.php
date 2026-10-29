<?php

declare(strict_types=1);

namespace App\Note\Service;

use AIC\Controller\Domain\CsvDocument;
use AIC\Database\Contracts\QueryException;
use App\Note\Form\StockQuery;
use App\Note\Repository\StockRepository;
use InvalidArgumentException;
use RuntimeException;

use function dirname;
use function file_put_contents;
use function is_dir;
use function mkdir;
use function rename;
use function tempnam;

/**
 * The stock as a CSV file (#209): the items a stock query matches, written
 * through `CsvDocument` to the project's `var/exports/stock.csv`, outside
 * `public/`. It writes a temporary file beside it and renames it over, so a
 * download never reads half a file.
 */
final readonly class StockExport
{
    /**
     * The export's columns.
     */
    private const array HEADER = ['name', 'price', 'added_on', 'certified', 'quantity'];

    /**
     * @param StockRepository $stockRepository The stock.
     * @param string          $root            The app's root directory.
     */
    public function __construct(
        private StockRepository $stockRepository,
        private string $root,
    ) {
    }

    /**
     * @param StockQuery $stockQuery What the export asks for: the stock list's search, prices and SKUs.
     *
     * @throws InvalidArgumentException When a constructed value is invalid.
     * @throws RuntimeException         When the stock cannot be read (a `QueryException`) or the file cannot be written.
     *
     * @return string The file's path.
     */
    public function file(StockQuery $stockQuery): string
    {
        $path = $this->directory() . '/stock.csv';
        self::write($path, (new CsvDocument(self::HEADER, $this->rows($stockQuery)))->text());

        return $path;
    }

    /**
     * @throws RuntimeException When the directory cannot be made.
     *
     * @return string The project's `var/exports`, made when it is missing.
     */
    private function directory(): string
    {
        $directory = $this->root . '/var/exports';

        if (!is_dir($directory) && !mkdir($directory, 0o775, true) && !is_dir($directory)) {
            throw new RuntimeException('The exports directory cannot be made: ' . $directory);
        }

        return $directory;
    }

    /**
     * @param StockQuery $stockQuery What the export asks for.
     *
     * @throws QueryException           When the stock cannot be read.
     * @throws InvalidArgumentException When a constructed value is invalid.
     *
     * @return list<list<string>> The matching items, oldest first, in the header's order.
     */
    private function rows(StockQuery $stockQuery): array
    {
        $rows = [];

        foreach ($this->stockRepository->all($stockQuery) as $stockItem) {
            $quantity = $stockItem->quantity === null
                ? ''
                : (string) $stockItem->quantity;
            $rows[] = [$stockItem->name, $stockItem->price, $stockItem->addedOn, self::yes($stockItem->certified), $quantity];
        }

        return $rows;
    }

    /**
     * @param string $path The file.
     * @param string $text What it holds.
     *
     * @throws RuntimeException When it cannot be written.
     */
    private static function write(string $path, string $text): void
    {
        $partial = tempnam(dirname($path), 'stock-');

        if ($partial === false || file_put_contents($partial, $text) === false || !rename($partial, $path)) {
            throw new RuntimeException('The stock export cannot be written: ' . $path);
        }
    }

    /**
     * @param bool $flag A flag.
     *
     * @return string `yes` or `no`.
     */
    private static function yes(bool $flag): string
    {
        return $flag
            ? 'yes'
            : 'no';
    }
}
