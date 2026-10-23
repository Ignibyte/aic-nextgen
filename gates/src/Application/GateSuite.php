<?php

declare(strict_types=1);

namespace Gates\Application;

use Gates\Domain\GateProfile;
use Gates\Domain\SuiteGate;

use function array_filter;
use function array_key_exists;
use function array_keys;
use function array_map;
use function array_slice;
use function array_values;
use function implode;
use function strtr;

use const PHP_BINARY;

/**
 * The gate suite (#116): the one list of every gate, its description, where it
 * runs and its command. `aic-gate` runs a row; the runner lists the rows. An
 * entry (`php`) is a script in this package's `bin/`, a tool (`bin`) is the
 * project's `vendor/bin/<tool>`; `{config}` in an argument names this
 * package's `config/`, where the base configs are, and `{classRoots}` the
 * profile's class roots (#130). AIC's own repository runs every row; an app
 * runs the `app` rows, since the `aic` rows (22, 28, 29, 31) hold AIC's own
 * docs, rules and made scaffold.
 */
final readonly class GateSuite
{
    /**
     * Every gate by N: its description, its scope, and its command (`php` and an entry, or `bin` and a tool).
     */
    private const array GATES = [
        0 => [
            'composer audit: no known security advisories in the locked dependencies.',
            'app',
            ['php', 'gate-composer-audit.php'],
        ],
        1 => [
            'composer-normalize: the root and framework manifests are in canonical order.',
            'app',
            ['php', 'gate-normalize-all.php'],
        ],
        2 => [
            'Runtime require allow-list: php, ext-*, psr/* and aic/framework only.',
            'app',
            ['php', 'gate-require-allowlist.php'],
        ],
        3 => [
            'Require-checker: every symbol the runtime code uses comes from a declared require.',
            'app',
            ['php', 'gate-require-checker.php'],
        ],
        4 => [
            'composer-unused: no required package goes unused.',
            'app',
            ['php', 'gate-composer-unused.php'],
        ],
        5 => [
            'No baselines, no suppressions.',
            'app',
            ['php', 'gate-no-baselines.php'],
        ],
        6 => [
            'Pint: code style.',
            'app',
            ['bin', 'pint', '--test', '--config=pint.json'],
        ],
        7 => [
            'Rector: no pending PHP 8.5 refactors (dry run).',
            'app',
            ['bin', 'rector', 'process', '--dry-run', '--no-progress-bar'],
        ],
        8 => [
            'PHPStan level 10, bleeding edge, with the strict rule sets.',
            'app',
            ['bin', 'phpstan', 'analyse', '--no-progress', '--memory-limit=2G', '--configuration=phpstan.neon'],
        ],
        9 => [
            'phpat: the architecture rules in gates/architecture.',
            'app',
            ['bin', 'phpstan', 'analyse', '--no-progress', '--memory-limit=2G', '--configuration=phpstan-architecture.neon'],
        ],
        10 => [
            'PHPMD: every ruleset, at the limits in phpmd.xml.',
            'app',
            [
                'bin',
                'phpmd',
                '{classRoots}',
                'text',
                '{config}/phpmd.xml,{config}/phpmd-custom.xml',
                '--strict',
                '--exclude',
                '*/modules/*/templates/*',
            ],
        ],
        11 => [
            'PHPCS: PSR-12 plus slevomat.',
            'app',
            ['bin', 'phpcs', '--standard=phpcs.xml.dist'],
        ],
        12 => [
            'Deptrac: the layer ladder inside each system.',
            'app',
            ['bin', 'deptrac', 'analyse', '--no-progress', '--fail-on-uncovered'],
        ],
        13 => [
            'Psalm level 1 with taint analysis.',
            'app',
            ['bin', 'psalm', '--no-progress', '--show-info=true', '--no-cache', '--no-diff'],
        ],
        14 => [
            'phpcpd: no copy-paste (min 4 lines, 50 tokens).',
            'app',
            ['php', 'gate-deduplicator.php'],
        ],
        15 => [
            'semgrep: the AIC ruleset in semgrep.yml.',
            'app',
            ['php', 'gate-semgrep.php'],
        ],
        16 => [
            'No runtime-dynamic require or include in runtime code.',
            'app',
            ['php', 'gate-no-dynamic-require.php'],
        ],
        17 => [
            'gitleaks: no secrets in the history.',
            'app',
            ['php', 'gate-gitleaks.php'],
        ],
        18 => [
            'shellcheck: every shell script, at warning level.',
            'app',
            ['php', 'gate-shellcheck.php'],
        ],
        19 => [
            'Documentation: no TODO markers, no dead relative links.',
            'app',
            ['php', 'gate-doc-todos.php'],
        ],
        20 => [
            'captainhook: the git hooks are installed.',
            'app',
            ['php', 'gate-captainhook.php'],
        ],
        21 => [
            'Native: every runtime class compiles, links, runs and matches PHP under elephc.',
            'app',
            ['php', 'gate-native.php'],
        ],
        22 => [
            'Pipeline docs: frontmatter, line caps and internal links of the pipeline docs and phase commands.',
            'aic',
            ['php', 'gate-pipeline-spec-sync.php'],
        ],
        23 => [
            'Native site: the app compiled with elephc --web answers every step as the interpreted app does.',
            'app',
            ['php', 'gate-native-site.php'],
        ],
        24 => [
            'Build outputs: the generated wiring, build/surface.json, the template classes, public/assets/ and the code '
                . 'wiki in docs/wiki/ match what composer run build writes; an orphan there is stale.',
            'app',
            ['php', 'aic-build', '--check'],
        ],
        25 => [
            'Templates: every component template (*.html.php) uses only the plain PHP template grammar; deny by default.',
            'app',
            ['php', 'gate-templates.php'],
        ],
        26 => [
            'Module boundaries: kind folders, the 15-file cap, exports and requires, no require cycles, nothing outside '
                . 'modules/ but the shell.',
            'app',
            ['php', 'gate-modules.php'],
        ],
        27 => [
            'RawHtml, counted: every use listed and held to its grant in config/raw-html.php; RawHtml:: only in the Renderer.',
            'app',
            ['php', 'gate-raw-html.php'],
        ],
        28 => [
            'House rules: every rule the workflow ships bites on its own probe, its held cases passing and its broken ones failing.',
            'aic',
            ['php', 'gate-house-rules.php'],
        ],
        29 => [
            'Architecture docs: framework/docs/ states the gates, kinds, folders, namespaces, declarations and names the '
                . "code has, its anchors resolve, and its prose passes no-ai-slop's mechanical check.",
            'aic',
            ['php', 'gate-architecture-docs.php'],
        ],
        30 => [
            "Suite files: the tools' root configs are aic/gates' stubs (phpcs.xml.dist with one root per module), and "
                . "composer.json carries the suite's scripts and plugins; generated custom tool registrations are fresh.",
            'app',
            ['php', 'gate-suite-files.php'],
        ],
        31 => [
            'Made scaffold: modules/scaffold/ is byte for byte what the ten bin/aic make:* lines make, the make commands run '
                . 'in memory over a project without it.',
            'aic',
            ['php', 'gate-make-scaffold.php'],
        ],
    ];

    /**
     * @param string         $root           The project's root.
     * @param string         $package        This package's directory.
     * @param ProjectProfile $projectProfile Which rows run here, and gate:10's paths (#130).
     */
    public function __construct(
        private string $root,
        private string $package,
        private ProjectProfile $projectProfile = new ProjectProfile(),
    ) {
    }

    /**
     * @return list<SuiteGate> Every gate, by N.
     */
    public function gates(): array
    {
        return array_values(array_filter(array_map($this->gate(...), array_keys(self::GATES)), $this->runsHere(...)));
    }

    /**
     * @param int $number A gate's N.
     *
     * @return SuiteGate|null The gate; null when the suite has no such N.
     */
    public function find(int $number): ?SuiteGate
    {
        $suiteGate = array_key_exists($number, self::GATES)
            ? $this->gate($number)
            : null;

        return $suiteGate instanceof SuiteGate && $this->runsHere($suiteGate)
            ? $suiteGate
            : null;
    }

    /**
     * @param SuiteGate $suiteGate A gate.
     *
     * @return bool Whether it runs in this project: every gate in AIC's repository, an `app` gate in an app.
     */
    private function runsHere(SuiteGate $suiteGate): bool
    {
        return $suiteGate->scope === 'app' || $this->projectProfile->profile($this->root) === GateProfile::Aic;
    }

    /**
     * @param int $number A gate's N, one the suite has.
     *
     * @return SuiteGate Its row, its command made absolute.
     */
    private function gate(int $number): SuiteGate
    {
        [$description, $scope, $command] = self::GATES[$number];
        $kind = $command[0];
        $program = $command[1];
        $arguments = array_slice($command, 2);
        $head = $kind === 'php'
            ? [PHP_BINARY, $this->package . '/bin/' . $program]
            : [$this->root . '/vendor/bin/' . $program];
        $replace = [
            '{classRoots}' => implode(',', $this->projectProfile->roots($this->root)->classRoots),
            '{config}' => $this->package . '/config',
        ];
        $tail = array_map(static fn (string $argument): string => strtr($argument, $replace), $arguments);

        return new SuiteGate($number, $description, $scope, [...$head, ...$tail]);
    }
}
