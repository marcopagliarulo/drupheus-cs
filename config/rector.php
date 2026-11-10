<?php

/**
 * @file
 * Provides the Rector configuration factory for contrib projects.
 */

declare(strict_types=1);

use Composer\InstalledVersions;
use DrupheusCs\CoreConstraint;
use DrupalFinder\DrupalFinderComposerRuntime;
use DrupalRector\Services\DrupalRectorSettings;
use DrupalRector\Set\Drupal10SetList;
use DrupalRector\Set\Drupal11SetList;
use Rector\Config\RectorConfig;
use Symfony\Component\Yaml\Yaml;

/**
 * Builds the Rector configuration for a contrib project.
 *
 * Deprecations newer than the lowest supported core are wrapped in
 * DeprecationHelper::backwardsCompatibleCall(), as drupal-rector recommends
 * for contrib. The lowest core is read from the .info.yml files in the paths.
 *
 * @param list<string> $paths
 *   The paths to process.
 * @param string|null $minimum_core_version
 *   (optional) The lowest supported core, overriding the .info.yml files.
 *
 * @return \Closure
 *   The Rector configuration callback.
 */
return static function (array $paths, ?string $minimum_core_version = NULL): Closure {
  $extensions = ['php', 'module', 'theme', 'install', 'profile', 'inc', 'engine'];
  if ($minimum_core_version === NULL) {
    $versions = [];
    foreach ($paths as $path) {
      foreach (glob(rtrim($path, '/') . '/*.info.yml') ?: [] as $file) {
        $info = Yaml::parseFile($file);
        $versions[] = is_string($info['core_version_requirement'] ?? NULL)
          ? CoreConstraint::lowestVersion($info['core_version_requirement'])
          : NULL;
      }
    }
    $versions = array_filter($versions);
    usort($versions, 'version_compare');
    $minimum_core_version = $versions[0] ?? '11.0.0';
  }

  // Rector does not follow symlinks, and drupal.org CI symlinks each top-level
  // item of a project into the module directory.
  $resolved_paths = [];
  foreach ($paths as $path) {
    $entries = is_dir($path) ? glob(rtrim($path, '/') . '/*') ?: [] : [];
    if (array_filter($entries, 'is_link') === []) {
      $resolved_paths[] = $path;
      continue;
    }
    foreach ($entries as $entry) {
      if (is_dir($entry) || in_array(pathinfo($entry, PATHINFO_EXTENSION), $extensions, TRUE)) {
        $resolved_paths[] = realpath($entry);
      }
    }
  }

  return static function (RectorConfig $config) use ($resolved_paths, $extensions, $minimum_core_version): void {
    $config->paths($resolved_paths);
    $config->sets([Drupal10SetList::DRUPAL_10, Drupal11SetList::DRUPAL_11]);
    $config->fileExtensions($extensions);
    $config->importNames(TRUE, FALSE);
    $config->importShortClasses(FALSE);
    $config->skip(['*/vendor/*', '*/node_modules/*']);

    $config->phpstanConfigs([InstalledVersions::getInstallPath('mglaman/phpstan-drupal') . '/extension.neon']);
    $drupal_root = (new DrupalFinderComposerRuntime())->getDrupalRoot();
    $config->autoloadPaths(array_map(
      static fn(string $directory): string => $drupal_root . '/' . $directory,
      ['core', 'modules', 'profiles', 'themes'],
    ));

    $config->singleton(
      DrupalRectorSettings::class,
      static fn(): DrupalRectorSettings => (new DrupalRectorSettings())
        ->enableBackwardCompatibility()
        ->setMinimumCoreVersionSupported($minimum_core_version),
    );
  };
};
