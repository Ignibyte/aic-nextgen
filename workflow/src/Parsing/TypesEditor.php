<?php

declare(strict_types=1);

namespace AIC\Workflow\Parsing;

use AIC\Workflow\Exception\WorkflowRefusal;
use PhpParser\Comment\Doc;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Namespace_;

use function array_filter;
use function array_values;
use function count;
use function rtrim;
use function str_ends_with;
use function strrpos;
use function substr;

/**
 * A module's entity types class, read and edited with php-parser (#328): one final class in a namespace whose tables
 * are its private `<entity>With()` methods, each behind a public `<entity>()` (`TypesMethods`). An edit adds public
 * methods before the first private one and private methods before the closing brace (the order Pint keeps), or a
 * relation to an accessor's list, at the parser's offsets, so every other byte stays; an edit that would pass PHPMD's
 * caps on a class is refused.
 */
final readonly class TypesEditor
{
    /**
     * PHPMD's `TooManyMethods` cap.
     */
    private const int METHODS = 15;

    /**
     * PHPMD's `ExcessiveClassLength` cap.
     */
    private const int LINES = 300;

    /**
     * @param ManifestSyntax $manifestSyntax Parses with names resolved and offsets kept.
     * @param TypesMethods   $typesMethods   Reads a table method and an accessor's relations.
     */
    public function __construct(
        private ManifestSyntax $manifestSyntax = new ManifestSyntax(),
        private TypesMethods $typesMethods = new TypesMethods(),
    ) {
    }

    /**
     * @param string $text Its source.
     * @param string $file Its path, for a refusal.
     *
     * @throws WorkflowRefusal When it is not a types class.
     *
     * @return array<string, string> Each table and its accessor, such as `scaffold_tasks` => `scaffoldTask`.
     */
    public function accessors(string $text, string $file): array
    {
        $accessors = [];

        foreach ($this->typesClass($text, $file)->getMethods() as $classMethod) {
            $table = $this->typesMethods->table($classMethod);

            if ($table === '') {
                continue;
            }

            $accessors[$table] = substr($classMethod->name->toString(), 0, -4);
        }

        return $accessors;
    }

    /**
     * @param string $text    Its source.
     * @param string $file    Its path, for a refusal.
     * @param string $public  Public methods, each line ending in a newline; empty for none.
     * @param string $private Private methods, the same way.
     *
     * @throws WorkflowRefusal When it is not a types class with a table, or the methods would take it past a cap.
     *
     * @return string Its source with the public methods before the first private one and the private ones last.
     */
    public function withMethods(string $text, string $file, string $public, string $private): string
    {
        $class = $this->typesClass($text, $file);
        $end = $class->getEndFilePos();
        $first = $this->firstPrivate($class, $text, $file);
        $edited = $private === ''
            ? $text
            : substr($text, 0, $end) . "\n" . $private . substr($text, $end);
        $edited = $public === ''
            ? $edited
            : substr($edited, 0, $first) . $public . "\n" . substr($edited, $first);
        $this->capped($this->typesClass($edited, $file), $file);

        return $edited;
    }

    /**
     * @param string $text     Its source.
     * @param string $file     Its path, for a refusal.
     * @param string $accessor A public accessor, such as `scaffoldTask`.
     * @param string $relation The relation's lines, each ending in a newline.
     *
     * @throws WorkflowRefusal When it is not a types class or the accessor is not of the shape the make edits.
     *
     * @return string Its source with the relation last in the accessor's list.
     */
    public function withRelation(string $text, string $file, string $accessor, string $relation): string
    {
        $array = $this->typesMethods->relations($this->typesClass($text, $file), $accessor, $file);
        $start = $array->getStartFilePos();
        $end = $array->getEndFilePos();
        $items = rtrim(substr($text, $start + 1, $end - $start - 1));
        $comma = $items === '' || str_ends_with($items, ',')
            ? ''
            : ',';

        return substr($text, 0, $start) . '[' . $items . $comma . "\n" . $relation . '        ]' . substr($text, $end + 1);
    }

    /**
     * @param string $text Its source.
     * @param string $file Its path, for a refusal.
     *
     * @throws WorkflowRefusal When it does not parse or holds anything but one class in one namespace.
     *
     * @return Class_ The class.
     */
    private function typesClass(string $text, string $file): Class_
    {
        $classes = [];

        foreach ($this->manifestSyntax->parse($text, $file) as $stmt) {
            $inner = $stmt instanceof Namespace_
                ? $stmt->stmts
                : [];
            $classes = [...$classes, ...self::classes($inner)];
        }

        $class = $classes[0] ?? null;

        if (count($classes) !== 1 || !$class instanceof Class_) {
            throw new WorkflowRefusal($file . ' is not one class in a namespace, which a make edits; add the change by hand.');
        }

        return $class;
    }

    /**
     * @param Class_ $class The types class.
     * @param string $text  Its source.
     * @param string $file  Its path, for a refusal.
     *
     * @throws WorkflowRefusal When it has no private method, so no table.
     *
     * @return int Where the line of its first private method begins, its docblock included.
     */
    private function firstPrivate(Class_ $class, string $text, string $file): int
    {
        $privates = array_values(
            array_filter($class->getMethods(), static fn (ClassMethod $classMethod): bool => $classMethod->isPrivate()),
        );
        $classMethod = $privates[0]
            ?? throw new WorkflowRefusal($file . ' declares no table (a private <entity>With() method); add the change by hand.');
        $docComment = $classMethod->getDocComment();
        $start = $docComment instanceof Doc
            ? $docComment->getStartFilePos()
            : $classMethod->getStartFilePos();
        $line = strrpos(substr($text, 0, $start), "\n");

        return $line === false
            ? 0
            : $line + 1;
    }

    /**
     * @param Class_ $class The edited class.
     * @param string $file  Its path, for a refusal.
     *
     * @throws WorkflowRefusal When it would hold more methods or lines than PHPMD allows a class.
     */
    private function capped(Class_ $class, string $file): void
    {
        $methods = count($class->getMethods());
        $lines = $class->getEndLine() - $class->getStartLine() + 1;

        if ($methods <= self::METHODS && $lines <= self::LINES) {
            return;
        }

        throw new WorkflowRefusal(
            $file . ' would hold ' . $methods . ' methods in ' . $lines . " lines, past PHPMD's " . self::METHODS
                . ' and ' . self::LINES . '; give these tables a module of their own.',
        );
    }

    /**
     * @param array<object> $nodes A namespace's statements.
     *
     * @return list<Class_> Its classes.
     */
    private static function classes(array $nodes): array
    {
        $classes = [];

        foreach ($nodes as $node) {
            if (!($node instanceof Class_)) {
                continue;
            }

            $classes[] = $node;
        }

        return $classes;
    }
}
