<?php

declare(strict_types=1);

namespace DrupheusCs\Sniffs\Files;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * Checks the names of an extension's configuration, docs and Composer package.
 *
 * Runs once per extension, on its info.yml file, and reports each problem
 * there with the name of the file at fault.
 */
final class ProjectFileNamesSniff implements Sniff {

  /**
   * Directories holding configuration provided by the extension.
   */
  private const array CONFIG_DIRECTORIES = ['config/install', 'config/optional'];

  /**
   * The longest unique configuration name.
   */
  private const int MAX_CONFIG_NAME = 250;

  /**
   * Base names that always identify a documentation file.
   */
  private const array DOCUMENTATION = [
    'readme',
    'install',
    'changelog',
    'todo',
    'upgrade',
    'contributing',
    'maintainers',
    'license',
    'copyright',
  ];

  /**
   * {@inheritdoc}
   */
  public function register(): array {
    return [T_INLINE_HTML];
  }

  /**
   * {@inheritdoc}
   */
  public function process(File $phpcsFile, $stackPtr): int {
    $file = $phpcsFile->getFilename();
    $base = basename($file);
    // Only the extension's own info file, not configuration such as
    // "x.info.yml" files of another kind.
    if (!str_ends_with($base, '.info.yml') || str_contains(substr($base, 0, -9), '.')) {
      return $phpcsFile->numTokens;
    }

    $extension = substr($base, 0, -9);
    $directory = dirname($file);
    $this->checkConfiguration($phpcsFile, $directory, $extension);
    $this->checkDocumentation($phpcsFile, $directory);
    $this->checkComposer($phpcsFile, $directory, $extension);
    return $phpcsFile->numTokens;
  }

  /**
   * Checks the names of the configuration the extension provides.
   */
  private function checkConfiguration(File $phpcsFile, string $directory, string $extension): void {
    foreach (self::CONFIG_DIRECTORIES as $subdirectory) {
      foreach (glob($directory . '/' . $subdirectory . '/*.yml') ?: [] as $path) {
        $name = basename($path, '.yml');
        $relative = $subdirectory . '/' . basename($path);
        if (strlen($name) > self::MAX_CONFIG_NAME) {
          $message = '%s: configuration names cannot exceed %s characters';
          $phpcsFile->addError($message, 0, 'ConfigNameLength', [$relative, self::MAX_CONFIG_NAME]);
        }
        // Configuration entities are named after the extension providing the
        // entity type, such as views.view.frontpage.
        if (!str_starts_with($name, $extension . '.') && !$this->isConfigEntity($path)) {
          $message = '%s: simple configuration must be named after the extension, such as %s.settings';
          $phpcsFile->addError($message, 0, 'ConfigName', [$relative, $extension]);
        }
      }
    }
  }

  /**
   * Checks whether a configuration file holds a configuration entity.
   *
   * Entities are named (extension).(config_prefix).(id) and their exports
   * carry keys simple configuration does not. Entity types do not agree on
   * the ID key, such as "type" for node.type, so either sign is enough.
   */
  private function isConfigEntity(string $path): bool {
    if (substr_count(basename($path, '.yml'), '.') >= 2) {
      return TRUE;
    }
    try {
      $data = Yaml::parseFile($path);
    }
    catch (ParseException) {
      return TRUE;
    }
    return is_array($data) && array_intersect_key($data, array_flip(['id', 'uuid', 'status', 'dependencies'])) !== [];
  }

  /**
   * Checks the documentation files at the root of the extension.
   */
  private function checkDocumentation(File $phpcsFile, string $directory): void {
    foreach (scandir($directory) ?: [] as $name) {
      if (str_starts_with($name, '.') || !is_file($directory . '/' . $name)) {
        continue;
      }

      $dot = strrpos($name, '.');
      $base = $dot === FALSE ? $name : substr($name, 0, $dot);
      $extension = $dot === FALSE ? '' : substr($name, $dot + 1);
      $isDocumentation = in_array(strtolower($extension), ['md', 'txt'], TRUE);
      if (!$isDocumentation && !in_array(strtolower($base), self::DOCUMENTATION, TRUE)) {
        continue;
      }

      if (!in_array($extension, ['md', 'txt'], TRUE)) {
        $phpcsFile->addError('%s: documentation files must use the .md or .txt extension, in lowercase', 0, 'DocumentationExtension', [$name]);
      }
      elseif ($base !== strtoupper($base)) {
        $message = '%s: documentation file names must be uppercase, such as %s.%s';
        $phpcsFile->addError($message, 0, 'DocumentationName', [$name, strtoupper($base), $extension]);
      }
    }
  }

  /**
   * Checks the Composer package name of the extension.
   */
  private function checkComposer(File $phpcsFile, string $directory, string $extension): void {
    $path = $directory . '/composer.json';
    if (!is_file($path)) {
      return;
    }
    $data = json_decode((string) file_get_contents($path), TRUE);
    if (!is_array($data) || !isset($data['name']) || !is_string($data['name'])) {
      return;
    }

    $expected = 'drupal/' . $extension;
    if ($data['name'] !== $expected) {
      $message = 'composer.json: the package name must be %s, not %s';
      $phpcsFile->addError($message, 0, 'ComposerPackageName', [$expected, $data['name']]);
    }
  }

}
