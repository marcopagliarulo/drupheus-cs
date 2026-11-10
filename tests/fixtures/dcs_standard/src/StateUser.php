<?php

declare(strict_types=1);

namespace Drupal\dcs_standard;

use Drupal\Core\State\StateInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Writes state and request attributes.
 */
final class StateUser {

  /**
   * Constructs a StateUser object.
   */
  public function __construct(
    private readonly StateInterface $state,
  ) {}

  /**
   * Stores values.
   */
  public function store(Request $request): void {
    $this->state->set('dcs_standard.last_run', 1);
    $this->state->set('dcs_standard_count', 1);
    $this->state->set('system.cron_last', 1);
    $this->state->set('last_run', 1);
    $this->state->setMultiple(['Dcs_Standard_Mixed' => 1]);
    $request->attributes->set('_dcs_standard_value', 1);
    $request->attributes->set('dcs_value', 1);
  }

}
