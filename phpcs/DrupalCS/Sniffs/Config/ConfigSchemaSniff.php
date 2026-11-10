<?php

declare(strict_types=1);

namespace DrupalCS\Sniffs\Config;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * Requires a schema for the configuration an extension owns.
 *
 * Runs once per extension, on its info.yml file. Configuration named after
 * another extension, such as views.view.frontpage, gets its schema from that
 * extension and is not checked. Schemas of enclosing extensions count, since
 * a submodule may rely on its parent's.
 */
final class ConfigSchemaSniff implements Sniff {

  /**
   * Directories holding configuration provided by the extension.
   */
  private const array CONFIG_DIRECTORIES = ['config/install', 'config/optional'];

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
    if (!str_ends_with($base, '.info.yml') || str_contains(substr($base, 0, -9), '.')) {
      return $phpcsFile->numTokens;
    }

    $extension = substr($base, 0, -9);
    $directory = dirname($file);
    $keys = NULL;
    foreach (self::CONFIG_DIRECTORIES as $subdirectory) {
      foreach (glob($directory . '/' . $subdirectory . '/' . $extension . '.*.yml') ?: [] as $path) {
        $keys ??= $this->schemaKeys($directory);
        $name = basename($path, '.yml');
        if (!$this->hasSchema($name, $keys)) {
          $message = '%s/%s.yml: no configuration schema defines %s; add it to config/schema/%s.schema.yml';
          $phpcsFile->addError($message, 0, 'Missing', [$subdirectory, $name, $name, $extension]);
        }
      }
    }
    return $phpcsFile->numTokens;
  }

  /**
   * Returns the schema keys of an extension and the extensions containing it.
   *
   * @return array<string, true>
   *   The keys.
   */
  private function schemaKeys(string $directory): array {
    $keys = [];
    for ($dir = $directory; $dir !== dirname($dir); $dir = dirname($dir)) {
      if ($dir !== $directory && glob($dir . '/*.info.yml') === []) {
        continue;
      }
      foreach (glob($dir . '/config/schema/*.schema.yml') ?: [] as $schema) {
        try {
          $data = Yaml::parseFile($schema);
        }
        catch (ParseException) {
          continue;
        }
        if (is_array($data)) {
          $keys += array_fill_keys(array_map('strval', array_keys($data)), TRUE);
        }
      }
    }
    return $keys;
  }

  /**
   * Checks whether a schema key matches a configuration name.
   *
   * Follows TypedConfigManager::getFallbackName(): the last parts are
   * replaced by wildcards one at a time, and trailing wildcards may collapse
   * into one, so "a.*" matches "a.b.c".
   *
   * @param string $name
   *   The configuration name.
   * @param array<string, true> $keys
   *   The schema keys.
   */
  private function hasSchema(string $name, array $keys): bool {
    if (isset($keys[$name])) {
      return TRUE;
    }
    while (TRUE) {
      $replaced = (string) preg_replace('/([^\.:]+)([\.:\*]*)$/', '*\2', $name);
      if ($replaced === $name) {
        return FALSE;
      }
      $oneStar = (string) preg_replace('/\.([:\.\*]*)$/', '.*', $replaced);
      if (isset($keys[$replaced]) || isset($keys[$oneStar])) {
        return TRUE;
      }
      $name = $replaced;
    }
  }

}
