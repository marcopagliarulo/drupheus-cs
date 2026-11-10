<?php

declare(strict_types=1);

namespace Drupal\dcs_standard;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a variable naming fixture.
 */
final class VariableNames extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'dcs_standard_variable_names';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $bad_local = $form_state->getValues();
    return $form + $bad_local;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
  }

  /**
   * Returns a value.
   */
  public function value(string $new_value): string {
    $camelLocal = $new_value;
    return $camelLocal;
  }

}
