<?php

declare(strict_types=1);

namespace Drupal\dcs_standard;

use Drupal\Core\Url, Drupal\Core\Link;

/**
 * Declares modifiers in the wrong order.
 */
class Modifiers {

  public final const LINKS = [Url::class, Link::class];

  final protected const ORDERED = 1;

}
