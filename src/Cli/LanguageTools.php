<?php

declare(strict_types=1);

namespace DrupalCS\Cli;

/**
 * Runs the Twig, JavaScript, CSS and spelling checks on a project.
 *
 * The settings mirror the jobs of the drupal.org GitLab templates. A project
 * outside core/ cannot discover core's configuration by itself, so core's
 * Prettier and dictionary settings are passed through temporary files.
 */
final class LanguageTools {

  /**
   * The tools this class can run.
   */
  public const array TOOLS = ['twig', 'eslint', 'stylelint', 'cspell'];

  /**
   * Directories that never belong to the checked project.
   */
  private const array SKIPPED_DIRECTORIES = ['vendor', 'node_modules', '.git'];

  /**
   * Temporary configuration files to delete after a run.
   *
   * @var list<string>
   */
  private array $temporaryFiles = [];

  /**
   * Constructs a LanguageTools object.
   */
  public function __construct(
    private readonly string $binDir,
    private readonly string $packageDir,
    private readonly ?string $coreDir,
  ) {}

  /**
   * Runs a tool on a path and returns its exit code.
   *
   * A Node tool that is not installed is skipped and counts as passed.
   */
  public function run(string $tool, string $path, bool $fix = FALSE): int {
    if ($this->coreDir === NULL) {
      echo "Skipped {$tool}: drupal/core is not installed.", PHP_EOL;
      return 0;
    }

    try {
      return match ($tool) {
        'twig' => $this->twig($path, $fix),
        'eslint' => $this->eslint($path, $fix),
        'stylelint' => $this->stylelint($path, $fix),
        'cspell' => $this->cspell($path),
        default => 2,
      };
    }
    finally {
      array_map('unlink', $this->temporaryFiles);
      $this->temporaryFiles = [];
    }
  }

  /**
   * Runs Twig-CS-Fixer with core's rules and the template docblock rule.
   */
  private function twig(string $path, bool $fix): int {
    putenv('DRUPAL_CS_CORE_DIR=' . $this->coreDir);
    return $this->execute($path, [
      $this->binDir . '/twig-cs-fixer',
      $fix ? 'fix' : 'lint',
      '--no-cache',
      '--config=' . $this->packageDir . '/config/twig-cs-fixer.php',
      '.',
    ]);
  }

  /**
   * Runs ESLint on JavaScript and YAML files with core's rules.
   */
  private function eslint(string $path, bool $fix): int {
    $binary = $this->nodeBinary('eslint');
    if ($binary === NULL) {
      return 0;
    }

    $extends = [$this->coreDir . '/.eslintrc.passing.json'];
    if (is_file($path . '/.eslintrc.json')) {
      $extends[] = realpath($path . '/.eslintrc.json');
    }
    $config = $this->temporaryJson([
      'root' => TRUE,
      'extends' => $extends,
      'rules' => ['prettier/prettier' => ['error', $this->prettierOptions('js'), ['usePrettierrc' => FALSE]]],
      // The GitLab templates exclude YAML from Prettier.
      'overrides' => [['files' => ['*.yml'], 'rules' => ['prettier/prettier' => 'off']]],
    ]);

    return $this->execute($path, [
      $binary,
      '--no-eslintrc',
      '--config=' . $config,
      '--resolve-plugins-relative-to=' . $this->coreDir,
      '--no-error-on-unmatched-pattern',
      '--ignore-pattern=*.es6.js',
      '--ignore-pattern=*.min.js',
      '--ignore-pattern=vendor/',
      '--ext=.js,.yml',
      ...($fix ? ['--fix'] : []),
      '.',
    ]);
  }

  /**
   * Runs Stylelint with core's rules, unless the project has its own.
   */
  private function stylelint(string $path, bool $fix): int {
    $binary = $this->nodeBinary('stylelint');
    if ($binary === NULL) {
      return 0;
    }

    $command = [
      $binary,
      '**/*.css',
      '--allow-empty-input',
      '--config-basedir=' . $this->coreDir,
      '--ignore-pattern=vendor/**',
    ];
    if (glob($path . '/{.stylelintrc*,stylelint.config.*}', GLOB_BRACE) === []) {
      $command[] = '--config=' . $this->temporaryJson([
        'extends' => [$this->coreDir . '/.stylelintrc.json'],
        'rules' => ['prettier/prettier' => [TRUE, $this->prettierOptions('css'), ['usePrettierrc' => FALSE]]],
      ]);
    }
    if ($fix) {
      $command[] = '--fix';
    }
    return $this->execute($path, $command);
  }

  /**
   * Runs CSpell with core's dictionaries and the project's own words.
   */
  private function cspell(string $path): int {
    $binary = $this->nodeBinary('cspell');
    if ($binary === NULL) {
      return 0;
    }

    $root = (string) realpath($path);
    $config = $this->temporaryJson($this->cspellConfig($root));
    return $this->execute($path, [
      $binary,
      '--config=' . $config,
      '--root=' . $root,
      '--no-progress',
      '--show-context',
      '--show-suggestions',
      '--no-must-find-files',
      '.',
    ]);
  }

  /**
   * Builds the CSpell configuration for a project.
   *
   * @return array<string, mixed>
   *   The configuration.
   */
  private function cspellConfig(string $root): array {
    $defaults = $this->readJson($this->packageDir . '/config/cspell.json');
    $project = is_file($root . '/.cspell.json') ? $this->readJson($root . '/.cspell.json') : [];
    $config = array_merge($defaults, $project);

    $names = ['license', 'copyright', 'maintainers', 'changelog', 'composer'];
    $words = [];
    $ignored = [];
    foreach ($this->projectFiles($root) as $file) {
      $name = basename($file);
      if (str_ends_with($name, '.info.yml')) {
        array_push($words, ...explode('_', basename($name, '.info.yml')));
      }
      if (in_array(strtolower((string) strstr($name, '.', TRUE)), $names, TRUE)) {
        $ignored[] = substr($file, strlen($root) + 1);
      }
    }

    $definitions = [
      ['name' => 'drupal', 'path' => $this->coreDir . '/misc/cspell/drupal-dictionary.txt'],
      ['name' => 'dictionary', 'path' => $this->coreDir . '/misc/cspell/dictionary.txt'],
    ];
    $dictionaries = ['dictionary', 'drupal', 'companies', 'fonts', 'html', 'php', 'softwareTerms'];
    if (is_file($root . '/.cspell-project-words.txt')) {
      $definitions[] = ['name' => 'project-words', 'path' => $root . '/.cspell-project-words.txt'];
      $dictionaries[] = 'project-words';
    }

    $config['version'] = '0.2';
    $config['globRoot'] = $root;
    $config['words'] = array_values(array_unique([
      ...$defaults['words'],
      ...($project['words'] ?? []),
      ...$words,
    ]));
    $config['ignorePaths'] = array_values(array_unique([
      ...$defaults['ignorePaths'],
      ...($project['ignorePaths'] ?? []),
      ...$ignored,
    ]));
    $config['dictionaries'] = array_values(array_unique([...($project['dictionaries'] ?? []), ...$dictionaries]));
    $config['dictionaryDefinitions'] = [...($project['dictionaryDefinitions'] ?? []), ...$definitions];
    return $config;
  }

  /**
   * Returns every file of a project, skipping dependency directories.
   *
   * @return list<string>
   *   The absolute file paths.
   */
  private function projectFiles(string $root): array {
    $skipped = self::SKIPPED_DIRECTORIES;
    $filter = new \RecursiveCallbackFilterIterator(
      new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
      static fn(\SplFileInfo $file): bool => !in_array($file->getFilename(), $skipped, TRUE),
    );

    $files = [];
    foreach (new \RecursiveIteratorIterator($filter) as $file) {
      $files[] = $file->getPathname();
    }
    return $files;
  }

  /**
   * Returns core's Prettier options for a language.
   *
   * @return array<string, mixed>
   *   The options.
   */
  private function prettierOptions(string $language): array {
    $settings = $this->readJson($this->coreDir . '/.prettierrc.json');
    $overrides = $settings['overrides'] ?? [];
    unset($settings['overrides']);

    if ($language === 'css') {
      foreach ($overrides as $override) {
        if (in_array('*.css', (array) $override['files'], TRUE)) {
          $settings = array_merge($settings, $override['options']);
        }
      }
    }
    return $settings;
  }

  /**
   * Returns a tool from core's node_modules, or NULL after reporting a skip.
   */
  private function nodeBinary(string $tool): ?string {
    $binary = $this->coreDir . '/node_modules/.bin/' . $tool;
    if (is_file($binary)) {
      return $binary;
    }
    echo "Skipped {$tool}: run `corepack yarn install` in {$this->coreDir}.", PHP_EOL;
    return NULL;
  }

  /**
   * Reads a JSON file.
   *
   * @return array<string, mixed>
   *   The decoded content.
   */
  private function readJson(string $file): array {
    return json_decode((string) file_get_contents($file), TRUE, flags: JSON_THROW_ON_ERROR);
  }

  /**
   * Writes a temporary JSON file and returns its path.
   *
   * @param array<string, mixed> $data
   *   The content.
   */
  private function temporaryJson(array $data): string {
    $base = (string) tempnam(sys_get_temp_dir(), 'drupal-cs');
    $file = $base . '.json';
    file_put_contents($file, json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    array_push($this->temporaryFiles, $base, $file);
    return $file;
  }

  /**
   * Runs a command from a directory and returns its exit code.
   *
   * @param string $directory
   *   The working directory.
   * @param list<string> $command
   *   The command and its arguments.
   */
  private function execute(string $directory, array $command): int {
    echo PHP_EOL, '> ', basename($command[0]), ' ', $directory, PHP_EOL;
    passthru('cd ' . escapeshellarg($directory) . ' && ' . implode(' ', array_map('escapeshellarg', $command)), $code);
    return $code;
  }

}
