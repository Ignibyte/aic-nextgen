<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Guard;

use AIC\Workflow\Domain\Guard\Invocation;

use function array_any;
use function array_slice;
use function file_get_contents;
use function in_array;
use function is_file;
use function preg_match;
use function str_contains;
use function str_starts_with;

/**
 * The wall on php (#117, from #118's spec): the guard records which tool ran,
 * not the code a shell hands php, so php that loads the project's autoloader
 * or names the workflow package could run the workflow's own classes and
 * forge the run's state. Such a php is refused, whether the code comes inline
 * (`-r`, `-B`, `-R`, `-E`), from a heredoc, from a script or through
 * `auto_prepend_file`; `-S` serves the app, which loads the autoloader.
 * `bin/aic` and `php -l` pass.
 */
final readonly class PhpWall
{
    /**
     * The options whose next word is their value; `-r`, `-B`, `-R` and `-E` hand php code inline.
     */
    private const array VALUED = ['-r', '-B', '-R', '-E', '-d', '-c', '-F', '-z'];

    /**
     * What the refusal tells the agent.
     */
    private const string WHY = "php that loads the project's autoloader or names the workflow package could forge the run's state: "
        . 'run bin/aic for the workflow and the app, composer run for the build and the gates, php -l to lint a file.';

    /**
     * @param string       $root         The project's root.
     * @param string       $package      The workflow package the guard runs from.
     * @param PathResolver $pathResolver Where a script lands.
     */
    public function __construct(
        private string $root,
        private string $package,
        private PathResolver $pathResolver,
    ) {
    }

    /**
     * @param Invocation $invocation A command.
     * @param string     $cwd        Where its paths resolve.
     *
     * @return string Why it is a php that reaches the workflow; empty when it is not php, or a php that does not.
     */
    public function why(Invocation $invocation, string $cwd): string
    {
        $words = $invocation->arguments();

        if (preg_match('/^php[0-9.]*$/', $invocation->program()) !== 1 || in_array('-l', $words, true)) {
            return '';
        }

        $scriptAt = self::scriptAt($words);

        $reaches = $this->options(array_slice($words, 0, $scriptAt))
            || $this->reaches($invocation->heredoc)
            || $this->script($words[$scriptAt] ?? '', $cwd);

        return $reaches
            ? self::WHY
            : '';
    }

    /**
     * @param list<string> $options php's words before its script.
     *
     * @return bool Whether one serves the app, prepends a file, or holds code or words that name the autoloader or the workflow.
     */
    private function options(array $options): bool
    {
        return array_any(
            $options,
            fn (string $word): bool => $word === '-S' || str_contains($word, 'auto_prepend_file')
                || str_contains($word, 'auto_append_file') || $this->reaches($word),
        );
    }

    /**
     * @param string $script The script php runs, as written; empty for none.
     * @param string $cwd    Where it resolves.
     *
     * @return bool Whether it is not `bin/aic` and its text loads the autoloader or names the workflow.
     */
    private function script(string $script, string $cwd): bool
    {
        $path = $script === ''
            ? ''
            : $this->pathResolver->absolute($script, $cwd);

        if ($path === '' || $path === $this->root . '/bin/aic' || !is_file($path)) {
            return $this->reaches($script);
        }

        return $this->reaches($script . "\n" . file_get_contents($path));
    }

    /**
     * @param string $text Code, a script's text, or words.
     *
     * @return bool Whether it names the autoloader, the workflow's namespace or the workflow package's files.
     */
    private function reaches(string $text): bool
    {
        return array_any(
            ['autoload', 'AIC\\Workflow', 'AIC\\\\Workflow', 'vendor/aic/workflow', $this->package],
            static fn (string $needle): bool => str_contains($text, $needle),
        ) || preg_match('#(^|[\\s\'"=])workflow/#', $text) === 1;
    }

    /**
     * @param list<string> $words php's arguments.
     *
     * @return int Where its script is: the first word that is no option or option value, or `-F`'s value.
     */
    private static function scriptAt(array $words): int
    {
        $index = 0;

        while (str_starts_with($words[$index] ?? '', '-') && $words[$index] !== '-F') {
            $index += in_array($words[$index], self::VALUED, true)
                ? 2
                : 1;
        }

        return ($words[$index] ?? '') === '-F'
            ? $index + 1
            : $index;
    }
}
