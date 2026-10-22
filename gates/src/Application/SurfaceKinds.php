<?php

declare(strict_types=1);

namespace Gates\Application;

use Gates\Domain\ClassKinds;
use Gates\Domain\KindMethods;
use ReflectionMethod;
use ReflectionNamedType;

use function in_array;
use function interface_exists;
use function method_exists;

/**
 * The surface's `kinds` table (#109): every kind with its contract, the
 * method the contract declares for it, that method's return type, the folder
 * it lives in and whether it is an entry point, read from `ClassKinds` and
 * `KindMethods` so the kinds have one source.
 */
final readonly class SurfaceKinds
{
    /**
     * @param ClassKinds  $classKinds  The kinds and their folders.
     * @param KindMethods $kindMethods The method each contract declares.
     */
    public function __construct(
        private ClassKinds $classKinds = new ClassKinds(),
        private KindMethods $kindMethods = new KindMethods(),
    ) {
    }

    /**
     * @return list<array{contract: string, entry: bool, folder: string, kind: string, method: string, response: string}> One row per kind.
     */
    public function rows(): array
    {
        $rows = [];

        foreach ($this->classKinds->table() as [$kind, $contract, $folder]) {
            $method = $this->kindMethods->methodOf($contract);
            $rows[] = [
                'contract' => $contract,
                'entry' => in_array($kind, $this->classKinds->entryKinds(), true),
                'folder' => $folder,
                'kind' => $kind,
                'method' => $method,
                'response' => self::response($contract, $method),
            ];
        }

        return $rows;
    }

    /**
     * @param string $contract A kind's interface; empty for a suffix kind.
     * @param string $method   Its kind method; empty for none.
     *
     * @return string The method's return type; empty when there is no method.
     */
    private static function response(string $contract, string $method): string
    {
        if ($method === '' || !interface_exists($contract) || !method_exists($contract, $method)) {
            return '';
        }

        $type = (new ReflectionMethod($contract, $method))->getReturnType();

        return $type instanceof ReflectionNamedType
            ? $type->getName()
            : '';
    }
}
