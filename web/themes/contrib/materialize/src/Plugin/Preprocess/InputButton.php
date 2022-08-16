<?php

namespace Drupal\materialize\Plugin\Preprocess;

use Drupal\materialize\Annotation\MaterializePreprocess;
use Drupal\materialize\Utility\Element;
use Drupal\materialize\Utility\Variables;

/**
 * Pre-processes variables for the "input__button" theme hook.
 *
 * @ingroup plugins_preprocess
 *
 * @MaterializePreprocess("input__button")
 */
class InputButton extends Input implements PreprocessInterface {

  /**
   * {@inheritdoc}
   */
  public function preprocessElement(Element $element, Variables $variables) {
    $element->addClass('btn');
    $element->setButtonSize();
    $element->colorize();
    // todo: fix icon handling.
    $element->setIcon($element->getProperty('icon'));
    // todo: fix icon_only buttons.
    // $variables['icon_only'] = $element->getProperty('icon_only');
    $variables['label'] = $element->getProperty('value');
    if ($element->getProperty('split')) {
      $variables->map([$variables::SPLIT_BUTTON]);
    }
    parent::preprocessElement($element, $variables);
  }

}
