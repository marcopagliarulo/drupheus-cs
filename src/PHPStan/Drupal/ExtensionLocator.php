<?php

declare(strict_types=1);

namespace DrupheusCs\PHPStan\Drupal;

use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * Finds the Drupal extension a file belongs to.
 */
final class ExtensionLocator {

  /**
   * Extension info keyed by directory; FALSE when the directory has none.
   *
   * @var array<string, array{name: string, dir: string, info: array<mixed>}|false>
   */
  private array $byDirectory = [];

  /**
   * Parsed services.yml files keyed by path.
   *
   * @var array<string, array<mixed>>
   */
  private array $services = [];

  /**
   * Returns the extensions containing a file, nearest first.
   *
   * @return list<array{name: string, dir: string, info: array<mixed>}>
   *   More than one entry when the file is inside a nested extension.
   */
  public function extensionsFor(string $file): array {
    $found = [];
    $dir = dirname($file);
    while ($dir !== dirname($dir)) {
      $extension = $this->extensionIn($dir);
      if ($extension !== FALSE) {
        $found[] = $extension;
      }
      $dir = dirname($dir);
    }
    return $found;
  }

  /**
   * Returns the parsed services.yml of an extension.
   *
   * @return array<mixed>
   *   The parsed file, empty if it is missing or invalid.
   */
  public function services(string $dir, string $name): array {
    $path = $dir . '/' . $name . '.services.yml';
    if (!isset($this->services[$path])) {
      $this->services[$path] = is_file($path) ? $this->parse($path) : [];
    }
    return $this->services[$path];
  }

  /**
   * Returns the extension whose info.yml is directly in a directory.
   *
   * @return array{name: string, dir: string, info: array<mixed>}|false
   *   The extension, or FALSE if the directory does not hold one.
   */
  private function extensionIn(string $dir): array|false {
    if (!isset($this->byDirectory[$dir])) {
      $this->byDirectory[$dir] = FALSE;
      foreach (glob($dir . '/*.info.yml') ?: [] as $path) {
        $this->byDirectory[$dir] = [
          'name' => basename($path, '.info.yml'),
          'dir' => $dir,
          'info' => $this->parse($path),
        ];
        break;
      }
    }
    return $this->byDirectory[$dir];
  }

  /**
   * Parses a YAML file, keeping custom tags such as !tagged_iterator.
   *
   * @return array<mixed>
   *   The parsed content, empty if the file is invalid.
   */
  private function parse(string $path): array {
    try {
      $parsed = Yaml::parseFile($path, Yaml::PARSE_CUSTOM_TAGS);
    }
    catch (ParseException) {
      return [];
    }
    return is_array($parsed) ? $parsed : [];
  }

}
