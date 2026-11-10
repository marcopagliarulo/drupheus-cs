# Changelog

## 1.0.0-alpha1

First public release.

- PHPCS standard `DrupheusCs`: Coder's Drupal and DrupalPractice standards
  plus sniffs for the coding standards rules Coder does not enforce.
- PHPStan extension: deprecation checks, native types, naming, PSR-4
  locations, and the extras for autowiring, class hooks, plugin attributes,
  constructor promotion, typed properties and variable case.
- Rector configuration with drupal-rector, plus `convert-hooks` and
  `promote` commands.
- Twig-CS-Fixer, ESLint, Stylelint and CSpell with core's configuration.
- `drupheus-cs` command: `init`, `check`, `fix`, `autowire`,
  `convert-hooks`, `promote`.

Known limitations:

- The Twig, ESLint and Stylelint steps of `drupheus-cs fix` are untested.
- Twig-CS-Fixer has only been tested with the fallback configuration used
  below Drupal 11.5.
- Rule names, identifiers and parameters may still change before 1.0.0.
