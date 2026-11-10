<?php

declare(strict_types=1);

namespace Drupal\dcs_standard\Plugin\Filter;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\filter\Attribute\Filter;
use Drupal\filter\FilterProcessResult;
use Drupal\filter\Plugin\FilterBase;
use Drupal\filter\Plugin\FilterInterface;

/**
 * Replaces placeholders.
 */
#[Filter(
  id: 'dcs_standard_placeholder',
  title: new TranslatableMarkup('Placeholder'),
  type: FilterInterface::TYPE_TRANSFORM_REVERSIBLE,
)]
final class PlaceholderFilter extends FilterBase {

  /**
   * {@inheritdoc}
   */
  public function process($text, $langcode): FilterProcessResult {
    $text = str_replace('[dcs_standard-break]', '<br>', $text);
    $text = str_replace('[break]', '<br>', $text);
    $text = (string) preg_replace('@\[dcs_standard-tag\](.+?)\[/tag-dcs_standard\]@', '$1', $text);
    $text = (string) preg_replace('@\[quote\]([a-z]+)\[/quote\]@', '$1', $text);
    return new FilterProcessResult($text);
  }

}
