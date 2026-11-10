<?php

namespace Drupal\dcs_standard;

/**
 * Provides a class breaking several rules.
 */
class DrupalThing {

  /**
   * A public property.
   *
   * @var string
   */
  public $label;

  /**
   * A promotable property.
   */
  protected string $name;

  /**
   * Constructs a DrupalThing object.
   */
  public function __construct(string $name) {
    $this->name = $name;
  }

  /**
   * Returns a generic object.
   */
  public function object(): \stdClass {
    return new \stdClass();
  }

}

/**
 * Provides a second class in the same file.
 */
class SecondThing {

}
