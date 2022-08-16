<?php

namespace Drupal\materialize\Plugin\Process;

use Drupal\materialize\Annotation\MaterializeProcess;
use Drupal\materialize\Utility\Element;
use Drupal\Core\Form\FormStateInterface;

/**
 * Processes the "details" element.
 *
 * @ingroup plugins_process
 *
 * @MaterializeProcess("details")
 */
class Details extends ProcessBase implements ProcessInterface {

  /**
   * {@inheritdoc}
   */
  public static function processElement(Element $element, FormStateInterface $form_state, array &$complete_form) {

    static $group_cache = [];

    $type = $element->getProperty('type');
    $title = $element->getProperty('title');

    static $first = TRUE;
    $group = $element->getProperty('group');
    if ($group) {

      if (!empty($group_cache[$group])) {
        $group_element = $group_cache[$group];
      }
      else {
        $form = $form_state->getCompleteForm();
        $group_element = Element::findChildByName($form, $group);
        if ($group_element) {
          $group_cache[$group] = $group_element;
        }
      }

      if (!empty($group_element['#type']) && 'vertical_tabs' == $group_element['#type']) {
        // Seems this element is in a vertical tab.
        // $element['#theme_wrappers'] = ['vertical_tab_panel'];.
        $element['#panel_type'] = 'vertical_tab_panel';
      }
    }
  }

}
