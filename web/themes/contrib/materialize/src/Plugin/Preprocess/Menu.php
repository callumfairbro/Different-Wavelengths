<?php

namespace Drupal\materialize\Plugin\Preprocess;

use Drupal\materialize\Annotation\MaterializePreprocess;
use Drupal\materialize\Materialize;
use Drupal\materialize\Plugin\Preprocess\PreprocessBase;
use Drupal\materialize\Plugin\Preprocess\PreprocessInterface;
use Drupal\materialize\Utility\Element;
use Drupal\materialize\Utility\Variables;
use Drupal\Component\Render\FormattableMarkup;

/**
 * Pre-processes variables for the "menu" theme hook.
 *
 * @ingroup plugins_preprocess
 *
 * @MaterializePreprocess("menu")
 */
class Menu extends PreprocessBase implements PreprocessInterface {

  /**
   * {@inheritdoc}
   */
  public function preprocessVariables(Variables $variables) {
    parent::preprocessVariables($variables);
    $variables['attributes']['class'][] = 'menu';
  }

}
