<?php

declare(strict_types=1);

namespace Drupal\dcs_standard\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides a block declared with an annotation.
 *
 * @Block(
 *   id = "dcs_standard_annotated",
 *   admin_label = @Translation("Annotated"),
 * )
 */
final class AnnotatedBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    return [];
  }

}
