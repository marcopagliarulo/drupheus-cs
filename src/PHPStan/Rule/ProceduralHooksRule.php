<?php

declare(strict_types=1);

namespace DrupheusCs\PHPStan\Rule;

use DrupheusCs\PHPStan\Drupal\CoreVersion;
use DrupheusCs\PHPStan\Drupal\Feature;
use PhpParser\Node;
use PhpParser\Node\Stmt\Function_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Reports procedural hooks that can be implemented with #[Hook].
 *
 * @implements \PHPStan\Rules\Rule<\PhpParser\Node\Stmt\Function_>
 */
final class ProceduralHooksRule implements Rule {

  /**
   * Hooks core refuses to run from a class.
   *
   * Mirrors HookCollectorPass::checkForProceduralOnlyHooks(). The requirements
   * hook is listed there too but has class-based replacements, handled apart.
   */
  private const array PROCEDURAL_ONLY = [
    'hook_info',
    'install',
    'install_tasks',
    'install_tasks_alter',
    'module_implements_alter',
    'removed_post_updates',
    'schema',
    'uninstall',
    'update_dependencies',
    'update_last_removed',
  ];

  private const string LEGACY_HOOK = 'Drupal\Core\Hook\Attribute\LegacyHook';

  private const string LEGACY_REQUIREMENTS_HOOK = 'Drupal\Core\Hook\Attribute\LegacyRequirementsHook';

  /**
   * Constructs a ProceduralHooksRule object.
   */
  public function __construct(
    private readonly CoreVersion $coreVersion,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function getNodeType(): string {
    return Function_::class;
  }

  /**
   * {@inheritdoc}
   */
  public function processNode(Node $node, Scope $scope): array {
    $file = $scope->getFile();
    $function = $node->name->toString();

    if ($this->hasAttribute($node, self::LEGACY_HOOK) && $this->coreVersion->supports($file, Feature::CLASS_HOOKS)) {
      return [$this->error($function . '() is a #[LegacyHook] shim, but this code no longer supports a core without class hooks: delete it.', 'drupheusCs.classHooks.legacyShim', $node)];
    }
    if ($this->hasAttribute($node, self::LEGACY_REQUIREMENTS_HOOK) && $this->coreVersion->supports($file, Feature::REQUIREMENTS_HOOKS)) {
      return [$this->error($function . '() is a #[LegacyRequirementsHook] shim, but this code no longer supports a core without the replacement hooks: delete it.', 'drupheusCs.classHooks.legacyShim', $node)];
    }

    $hook = $this->implementedHook($node);
    if ($hook === NULL) {
      return [];
    }

    if ($hook === 'requirements') {
      if (!$this->coreVersion->supports($file, Feature::REQUIREMENTS_HOOKS)) {
        return [];
      }
      return [$this->error($function . '() implements hook_requirements(): use #[Hook(\'runtime_requirements\')], #[Hook(\'update_requirements\')] and an InstallRequirementsInterface class.', 'drupheusCs.classHooks.requirements', $node)];
    }

    if (!$this->canUseClass($hook, $file)) {
      return [];
    }
    return [$this->error(sprintf("%s() implements hook_%s(): move it to a class in src/Hook using #[Hook('%s')].", $function, $hook, $hook), 'drupheusCs.classHooks.procedural', $node)];
  }

  /**
   * Returns the hook named in a function's "Implements hook_x()." docblock.
   */
  private function implementedHook(Function_ $node): ?string {
    $comment = $node->getDocComment()?->getText() ?? '';
    return preg_match('/Implements\s+hook_(\w+)\(\)/', $comment, $matches) === 1 ? $matches[1] : NULL;
  }

  /**
   * Checks whether a hook can be a class method on the supported core.
   */
  private function canUseClass(string $hook, string $file): bool {
    if (in_array($hook, self::PROCEDURAL_ONLY, TRUE) || preg_match('/^(post_update_|update_(\d+|N)$)/', $hook) === 1) {
      return FALSE;
    }

    $feature = Feature::CLASS_HOOKS;
    if (str_starts_with($hook, 'preprocess')) {
      $feature = Feature::PREPROCESS_CLASS_HOOKS;
    }
    if (str_ends_with($file, '.theme')) {
      $feature = Feature::THEME_CLASS_HOOKS;
    }
    return $this->coreVersion->supports($file, $feature);
  }

  /**
   * Checks whether a function has a given attribute.
   */
  private function hasAttribute(Function_ $node, string $attribute): bool {
    foreach ($node->attrGroups as $group) {
      foreach ($group->attrs as $candidate) {
        if ($candidate->name->toString() === $attribute) {
          return TRUE;
        }
      }
    }
    return FALSE;
  }

  /**
   * Builds an error on a function.
   */
  private function error(string $message, string $identifier, Function_ $node): IdentifierRuleError {
    return RuleErrorBuilder::message($message)->identifier($identifier)->line($node->getStartLine())->build();
  }

}
