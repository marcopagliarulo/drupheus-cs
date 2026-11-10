# Drupheus CS

Drupal coding standards, deprecation checks and modernization rules for
Drupal 11+ contrib development. It builds on PHP_CodeSniffer with
[Coder](https://www.drupal.org/project/coder) and Slevomat, PHPStan with
[phpstan-drupal](https://github.com/mglaman/phpstan-drupal), and
[drupal-rector](https://github.com/palantirnet/drupal-rector).

Drupal 10 is not supported. See [Coding standards coverage](#coding-standards-coverage)
for how each rule of the Drupal coding standards is enforced.

Drupheus CS is an independent project, not affiliated with or endorsed by the
Drupal Association. Drupal is a registered trademark of Dries Buytaert.

## Install

Install it in a Drupal 11 project that contains your module, because PHPStan
and Rector need Drupal core to analyze against.

```shell
composer config allow-plugins.dealerdirect/phpcodesniffer-composer-installer true
composer config allow-plugins.phpstan/extension-installer true
composer require --dev 'drupheus/drupheus-cs:^1.0@alpha'
vendor/bin/drupheus-cs init web/modules/contrib/my_module
```

The package is in alpha: `@alpha` allows it without lowering the project's
`minimum-stability`. Rule names and parameters may still change before 1.0.0.
See [CHANGELOG.md](CHANGELOG.md).

`init` writes `phpcs.xml.dist`, `phpstan.neon.dist` and `rector.php` to the
current directory. They are ordinary configuration files, so editors and CI
can use them directly.

ESLint, Stylelint and CSpell use the Node packages that ship with Drupal core. Install them once; until then those three checks are skipped with a message:

```shell
cd web/core && yarn install
```

## Commands

| Command | Purpose |
| --- | --- |
| `drupheus-cs check [tool...]` | Run all checks, or only the named ones: `phpcs`, `phpstan`, `rector`, `twig`, `eslint`, `stylelint`, `cspell` |
| `drupheus-cs fix` | Apply Rector deprecation fixes, then `phpcbf`, Twig-CS-Fixer, ESLint and Stylelint fixes |
| `drupheus-cs autowire` | List what can be autowired, what will fail, and what cannot, with reasons |
| `drupheus-cs convert-hooks <path>` | Convert procedural hooks to class hooks |
| `drupheus-cs promote <path>` | Convert constructor assignments to promoted properties |

Run `convert-hooks` and `promote` after `fix`, and review the result.

`convert-hooks` comes from drupal-rector and keeps a `#[LegacyHook]` wrapper for
each hook. If your module supports only Drupal 11.1 or later, delete the wrappers
and the generated `services.yml` entry, because `Hook` classes are registered
automatically. The `classHooks` extra reports the wrappers. The converter does
not check your minimum core version, so on 11.1 keep preprocess hooks
procedural.

## GitLab CI on drupal.org

The [GitLab templates](https://project.pages.drupalcode.org/gitlab_templates/)
already run PHPCS and PHPStan with your module's own configuration, so the
package only has to be installed and selected. CI can only install a published
package.

1. Add `"drupheus/drupheus-cs": "^1.0@alpha"` to `require-dev` in the module's
   `composer.json`. It is ignored when others install your module.
2. In `phpcs.xml.dist`, replace the `Drupal` and `DrupalPractice` rules with
   `<rule ref="DrupheusCs"/>`, and add
   `<config name="ignore_warnings_on_exit" value="1"/>`.
3. Keep your own `phpstan.neon.dist`. The rules load automatically.
4. Add a Rector job to `.gitlab-ci.yml`, because the templates do not have one:

```yaml
rector:
  stage: validate
  needs: [composer]
  allow_failure: true
  script:
    - cd $CI_PROJECT_DIR
    - vendor/bin/drupheus-cs init $DRUPAL_PROJECT_FOLDER
    - vendor/bin/drupheus-cs check rector
```

The templates already run Twig-CS-Fixer (Drupal 11.5 and later), ESLint, Stylelint and CSpell, so those need no setup.

The templates mark these validation jobs as allowed to fail. Set
`_ALL_VALIDATE_ALLOW_FAILURE: 0` to make them block the pipeline.

## Coding standards coverage

Every section of the [Drupal coding standards](https://project.pages.drupalcode.org/coding_standards/), and what covers each rule.

**Yes** means a tool reports it. **Partial** means only part of the rule is checked. **Manual** means no tool here checks it, so it needs human review.

### PHP coding standards

| Rule | Coverage | Covered by |
| --- | --- | --- |
| Short array syntax | Yes | `Drupal.Arrays.DisallowLongArraySyntax` |
| Array spacing | Yes | `Squiz.Arrays.ArrayBracketSpacing`, `Drupal.Arrays.Array` |
| Multi-line arrays: one element per line, trailing comma | Yes | `Drupal.Arrays.Array` |
| Space after a cast | Yes | `Generic.Formatting.SpaceAfterCast` |
| Methods return `$this` when there is nothing else to return | Manual | |
| Multi-line chains indented 2 spaces | Yes | `Drupal.WhiteSpace.ObjectOperatorIndent` |
| `new` always uses parentheses | Yes | `Drupal.Classes.ClassCreateInstance` |
| Control structures always use braces | Yes | `Drupal.ControlStructures.InlineControlStructure` |
| `elseif`, not `else if` | Yes | `Drupal.ControlStructures.ElseIf` |
| Keyword spacing and brace placement | Yes | `Drupal.ControlStructures.ControlSignature` |
| Alternate `:` syntax only in templates | Yes | `DrupheusCs.ControlStructures.AlternativeSyntax` (allowed in `.tpl.php` files) |
| One class, interface or trait per file | Yes | `Generic.Files.OneObjectStructurePerFile` |
| File named after its class | Yes | `Drupal.Classes.ClassFileName` |
| Classes in `.module` files only when safe | Manual | |
| `example.com` for example URLs | Manual | |
| Function call spacing | Yes | `PEAR.Functions.FunctionCallSignature`, `Generic.Functions.FunctionCallArgumentSpacing` |
| Function declaration format, multi-line trailing comma | Yes | `Drupal.Functions.FunctionDeclaration`, `Drupal.Functions.MultiLineFunctionDeclaration`, `Squiz.Functions.FunctionDeclarationArgumentSpacing` |
| `require_once` and `include_once` used as statements | Yes | `PEAR.Files.IncludingFile` |
| 2-space indent, no tabs | Yes | `Drupal.WhiteSpace.ScopeIndent`, `Generic.WhiteSpace.DisallowTabIndent` |
| No trailing whitespace | Yes | `Squiz.WhiteSpace.SuperfluousWhitespace` |
| `\n` line endings, one final newline | Yes | `Generic.Files.LineEndings`, `Drupal.Files.EndFileNewline` |
| Blank lines around namespaces, `use` blocks and class members | Yes | `Drupal.WhiteSpace.Namespace`, `Drupal.Classes.ClassDeclaration`, `Squiz.WhiteSpace.FunctionSpacing` |
| Prefer factories over direct instantiation | Manual | |
| Lines at most 80 characters | Partial | `Drupal.Files.LineLength` checks comments only |
| Conditions are not wrapped or over-compacted | Manual | |
| Functions lowercase with a module prefix | Yes | `Drupal.NamingConventions.ValidFunctionName` |
| Variables do not mix camelCase and snake_case in a file | Partial | `DrupheusCs.NamingConventions.VariableCaseConsistency` (warning, ignores parameters) |
| Persistent variables are lowercase and prefixed | Yes | `StateKeyRule` (PHPStan), applied to State API keys, which replaced `variable_set()`; literal keys only |
| Constants uppercase | Yes | `Generic.NamingConventions.UpperCaseConstantName` |
| `TRUE`, `FALSE`, `NULL` uppercase | Yes | `Generic.PHP.UpperCaseConstant` |
| Module constants prefixed | Yes | `Drupal.Semantics.ConstantName` |
| `const` rather than `define()` | Yes | `DrupalPractice.Constants.GlobalDefine` (warning) |
| Globals start with `_module_` | Yes | `Drupal.NamingConventions.ValidGlobal` |
| Classes UpperCamel, no underscores | Yes | `Drupal.NamingConventions.ValidClassName` |
| Methods and properties lowerCamel | Yes | `Drupal.NamingConventions.ValidFunctionName`, `Drupal.NamingConventions.ValidVariableName` |
| Acronyms in CamelCase (`XmlParser`) | Yes | `NamingConventionsRule` (PHPStan) |
| No "Drupal" or "Class" in class names | Yes | `NamingConventionsRule` (PHPStan) |
| `Interface`, `Trait`, `Test` suffixes only where they apply | Yes | `NamingConventionsRule` (PHPStan), `Drupal.Classes.InterfaceName` |
| No underscore prefix on protected or private members | Yes | `Drupal.NamingConventions.ValidVariableName`, `Drupal.NamingConventions.ValidFunctionName` |
| Class names stand alone | Manual | |
| Enums follow class naming, UpperCamel cases | Yes | `Drupal.NamingConventions.ValidEnumCase` |
| Documentation files named `README.md` style | Yes | `DrupheusCs.Files.ProjectFileNames` (files at the extension root) |
| Spaces around binary operators, none around unary | Yes | `Squiz.WhiteSpace.OperatorSpacing`, `Drupal.Formatting.SpaceUnaryOperator` |
| `!=`, never `<>` | Yes | `DrupheusCs.Operators.NotEqualOperator` |
| `?:` when the first operand matches the condition | Yes | `SlevomatCodingStandard.ControlStructures.RequireShortTernaryOperator` |
| `??` instead of an `isset()` ternary | Yes | `SlevomatCodingStandard.ControlStructures.RequireNullCoalesceOperator` |
| New code has parameter and return type hints | Yes | `NativeTypeHintsRule` (PHPStan) |
| `mixed` and unions only when needed | Manual | |
| Nullable types where the value can be null | Yes | `SlevomatCodingStandard.TypeHints.NullableTypeForNullDefaultValue` for parameters, `NullableReturnRule` (PHPStan) for return types |
| Most specific interface as object type | Manual | |
| No `stdClass` type hint | Yes | `NativeTypeHintsRule` (PHPStan) |
| `void` when nothing is returned | Yes | `NativeTypeHintsRule` (PHPStan) asks for `void` when the body returns nothing |
| Only `<?php`, no closing `?>` | Yes | `Generic.PHP.DisallowShortOpenTag`, `Zend.Files.ClosingTag` |
| Single quotes by default | Yes | `Squiz.Strings.DoubleQuoteUsage.NotRequired` (warning, fixable) |
| Semicolons required | Yes | PHP syntax |
| `declare(strict_types=1)` placement and format | Yes | `SlevomatCodingStandard.TypeHints.DeclareStrictTypes` |
| Spaces around `.` and `.=` | Yes | `Squiz.Strings.ConcatenationSpacing` |
| Provide interfaces for classes meant to be extended | Manual | |
| Visibility declared on methods and properties | Yes | `Drupal.Scope.MethodScope`, `Drupal.Classes.PropertyDeclaration` |
| Public properties discouraged | Yes | `SlevomatCodingStandard.Classes.ForbiddenPublicProperty` (warning, allows `readonly`) |
| Modifier order | Yes | `PSR2.Methods.MethodDeclaration` (`Drupal.Methods.MethodDeclaration` in Coder 8.3), `PSR2.Classes.PropertyDeclaration`, `DrupheusCs.Classes.ConstantModifierOrder` |

### API documentation and comments

| Rule | Coverage | Covered by |
| --- | --- | --- |
| Every function, class, member, constant and file documented | Yes | `Drupal.Commenting.FunctionComment`, `ClassComment`, `VariableComment`, `FileComment` |
| Docblock format, `*` on each line | Yes | `Drupal.Commenting.DocComment`, `DocCommentStar`, `DocCommentAlignment` |
| Summary capitalized, ends with a period | Yes | `Drupal.Commenting.DocComment` |
| Comment lines wrap at 80 characters | Yes | `Drupal.Files.LineLength` |
| Third-person verb summaries, proper grammar, current behavior only | Manual | |
| US English spelling | Yes | CSpell |
| No gendered pronouns | Yes | `Drupal.Commenting.GenderNeutralComment` (Coder addition) |
| `Implements hook_x().`, no `@param` or `@return` | Yes | `Drupal.Commenting.HookComment` |
| Callback, form, render, theme and preprocess summary wording | Manual | |
| `{@inheritdoc}` form | Yes | `Drupal.Commenting.DocComment` |
| Fully-qualified class names after tags | Yes | `Drupal.Commenting.DataTypeNamespace` |
| Type spelling: `bool`, `int`, `array`, `null` | Yes | `Drupal.Commenting.FunctionComment` |
| `object` rather than `stdClass` in docblocks | Yes | `DrupheusCs.Commenting.ObjectType` (fixable) |
| `@param` present with a description | Yes | `Drupal.Commenting.FunctionComment` |
| `@return` present when returning, absent when not | Yes | `Drupal.Commenting.FunctionComment` |
| Tag description indented two spaces | Yes | `Drupal.Commenting.FunctionComment` |
| Section order | Partial | `Drupal.Commenting.DocComment` checks `@param` first and tag grouping |
| Inline comments: spacing, capital letter, period, no `#` | Yes | `Drupal.Commenting.InlineComment` |
| Comments on their own line | Yes | `Drupal.Commenting.PostStatementComment` |
| Inline `@var` uses the full class name | Yes | `Drupal.Commenting.InlineVariableComment` |
| `@deprecated` format | Yes | `Drupal.Commenting.Deprecated` |
| `@file` required, absent from PSR-4 files | Yes | `Drupal.Commenting.FileComment` |
| `@see`: one per line, no extra text | Yes | `Drupal.Commenting.FunctionComment` |
| `@throws` class on the same line | Partial | `Drupal.Commenting.FunctionComment` |
| `@todo` format | Yes | `Drupal.Commenting.TodoComment` |
| `@code`, `@link`, `@section`, `@ref`, `@defgroup`, `@Event` usage | Manual | |
| Plugin annotation format | Manual | Use attributes instead, see the `pluginAttributes` extra |

### Other PHP

| Rule | Coverage | Covered by |
| --- | --- | --- |
| Namespaces: `use` rather than inline full names | Yes | `Drupal.Classes.FullyQualifiedNamespace` |
| Namespaces: global classes fully qualified, not imported | Yes | `Drupal.Classes.UseGlobalClass` |
| Namespaces: no leading `\` in `use` | Yes | `Drupal.Classes.UseLeadingBackslash` |
| Namespaces: one class per `use` | Yes | `PSR2.Namespaces.UseDeclaration`, including group `use` |
| Namespaces: alias only to avoid collisions | Yes | `DrupheusCs.Namespaces.UnnecessaryAlias` |
| Exceptions end in `Exception` | Yes | `NamingConventionsRule` (PHPStan) |
| Exception messages not translated | Yes | `DrupalPractice.General.ExceptionT` |
| Exception naming, subclasses and message content | Manual | |
| `catch` on a new line | Yes | `Drupal.ControlStructures.ControlSignature` |
| PSR-4: class location matches its namespace | Yes | `Psr4LocationRule` (PHPStan) for `src/` and `tests/src/`, `Drupal.Classes.ClassFileName` |
| E_ALL: no undefined variables | Yes | `DrupalPractice.CodeAnalysis.VariableAnalysis`, PHPStan |
| E_ALL: array indexes declared | Partial | PHPStan, where it knows the array shape |
| Services: request attributes prefixed with `_` | Yes | `RequestAttributeRule` (PHPStan), literal keys only |
| Placeholders prefixed with the module name | Yes | `FilterPlaceholderRule` (PHPStan), strings in filter plugins |

### Other languages

| Section | Coverage | Covered by |
| --- | --- | --- |
| Accessibility | Manual | no rules yet on [The Drupal coding standards for Accessibility](https://project.pages.drupalcode.org/coding_standards/accessibility/accessibility/) |
| CSS format | Yes | Stylelint with core's configuration |
| CSS architecture, file organization | Manual | |
| JavaScript coding standards | Yes | ESLint with core's configuration |
| JavaScript best practices, documentation, jQuery | Partial | ESLint with core's configuration |
| Twig spacing and formatting | Yes | Twig-CS-Fixer with core's configuration |
| Twig: docblock at the top of each template | Yes | `TemplateDocBlockRule` (Twig-CS-Fixer) |
| Twig: docblock content and expression conventions | Manual | |
| Spelling | Yes | CSpell with core's dictionaries, plus `.cspell-project-words.txt` |
| SQL: reserved words, capitalization, naming, `SELECT *` | Manual | |
| Markup | Manual | Standards not yet available |
| YAML: syntax, two-space indent | Yes | ESLint with core's configuration |
| YAML: configuration file names | Yes | `DrupheusCs.Files.ProjectFileNames` (simple configuration in `config/install` and `config/optional`) |
| Composer package names | Yes | `DrupheusCs.Files.ProjectFileNames` (`composer.json` next to the info file is `drupal/<machine name>`) |

## Extras

Extras go beyond the coding standards. All are enabled by default.

| Extra | Reports | Tool |
| --- | --- | --- |
| Deprecated APIs (always on) | Use of deprecated Drupal and PHP APIs, with fixes from drupal-rector | PHPStan, Rector |
| `variableCase` | Local variables and parameters not using the configured style, `lowerCamelCase` by default | PHPStan |
| `constructorPromotion` | Properties that can be promoted to the constructor | PHPStan |
| `autowire` | `create()` factories and `services.yml` arguments that can use autowiring; autowired constructors that will fail at runtime | PHPStan |
| `classHooks` | Procedural hooks that can use `#[Hook]`; leftover `#[LegacyHook]` shims | PHPStan |
| `pluginAttributes` | Plugins declared with annotations | PHPStan |
| `typedProperties` | Properties without a native type | PHPStan |
| strict types | Files without `declare(strict_types=1)` | PHPCS |
| config schema | Configuration in `config/install` and `config/optional` named after the extension but without a schema | PHPCS |

Disable PHPStan extras in `phpstan.neon.dist`:

```neon
parameters:
	drupheusCs:
		extras:
			autowire: false
```

Disable strict types in `phpcs.xml.dist`:

```xml
<rule ref="DrupheusCs">
  <exclude name="SlevomatCodingStandard.TypeHints.DeclareStrictTypes.DeclareStrictTypesMissing"/>
</rule>
```

To enforce `snake_case` instead of `lowerCamelCase`:

```neon
parameters:
	drupheusCs:
		variableCase:
			style: snake_case
```

Parameters named by a parent method or a hook, such as `$form_state`, are exempt.
Under `snake_case`, promoted constructor parameters are exempt too, because they
are also properties.

The `drupheusCs.standard` parameters `nativeTypeHints` and `namingConventions`
turn off the PHPStan rules for the standards themselves. Adding native types to
existing public methods is a BC break, so a released module may want to do that
or use a PHPStan baseline. `namingConventions` also covers State API keys,
request attributes, filter placeholders and PSR-4 locations, and
`nativeTypeHints` covers nullable return types. To skip only one of those,
ignore its identifier: `drupheusCs.naming.stateKey`,
`drupheusCs.naming.requestAttribute`, `drupheusCs.naming.placeholder`,
`drupheusCs.naming.psr4` or `drupheusCs.types.nullable`.

A single PHPCS check can be turned off the same way as strict types, for
example `<exclude name="DrupheusCs.Files.ProjectFileNames.ComposerPackageName"/>`
when the drupal.org project name differs from the module's machine name, or
`<exclude name="DrupheusCs.Config.ConfigSchema"/>` to stop requiring a schema.

## Minimum core version

A recommendation is only made when the code can use it on every core it
supports. The lowest supported core is read from `core_version_requirement` in
the nearest `.info.yml`. Set `drupheusCs.minimumCoreVersion` to override it.

| Feature | Needs core |
| --- | --- |
| Autowiring through `AutowireTrait`, and in controllers | 11.0 |
| Class hooks | 11.1 |
| Preprocess class hooks, requirements hooks | 11.2 |
| Plugin autowiring, class hooks in themes | 11.3 |
| Form autowiring without `AutowireTrait` | 11.4 |

Autowiring checks use the services of the Drupal site being analyzed, so
modules your code depends on must be installed in that site.

## Development

```shell
composer install
DRUPHEUS_CS_SITE=/path/to/drupal/project vendor/bin/phpunit
```

Without `DRUPHEUS_CS_SITE`, the integration tests are skipped. The project must
have this package installed, and the fixture modules are copied into its
`web/modules/custom` directory.
