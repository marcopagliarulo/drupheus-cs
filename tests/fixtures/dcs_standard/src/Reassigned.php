<?php

declare(strict_types=1);

namespace Drupal\dcs_standard;

/**
 * Provides a promotion fixture.
 */
final class Reassigned {

  /**
   * A value.
   */
  protected string $value;

  /**
   * Constructs a Reassigned object.
   */
  public function __construct(string $value) {
    $value = trim($value);
    $this->value = $value;
  }

}
