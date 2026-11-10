<?php

declare(strict_types=1);

namespace Drupal\dcs_standard;

use Drupal\Component\Utility\Html as HtmlUtility;
use Drupal\Core\Form\FormStateInterface as FormStateInterface;
use Drupal\Core\Render\Element\Link as RenderLink;
use Drupal\Core\Link as CoreLink;

/**
 * Uses aliases, double quotes, stdClass and the alternate syntax.
 */
final class Aliases {

  /**
   * Builds a list.
   *
   * @param \stdClass $item
   *   The item.
   *
   * @return array<int, mixed>
   *   The list.
   */
  public function build(object $item): array {
    $list = [HtmlUtility::class, FormStateInterface::class, RenderLink::class, CoreLink::class];
    if ($list):
      $list[] = "plain";
      $list[] = "It's fine";
      $list[] = "Contains $item->label";
    endif;
    return $list;
  }

}
