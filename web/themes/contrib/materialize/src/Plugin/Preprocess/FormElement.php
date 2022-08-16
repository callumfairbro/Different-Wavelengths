<?php

namespace Drupal\materialize\Plugin\Preprocess;

use Drupal\materialize\Annotation\MaterializePreprocess;
use Drupal\materialize\Utility\Element;
use Drupal\materialize\Utility\Variables;

/**
 * Pre-processes variables for the "form_element" theme hook.
 *
 * @ingroup plugins_preprocess
 *
 * @MaterializePreprocess("form_element")
 */
class FormElement extends PreprocessBase implements PreprocessInterface {

  /**
   * {@inheritdoc}
   */
  public function preprocessElement(Element $element, Variables $variables) {
    // Set has_error flag.
    $variables['has_error'] = $element->getProperty('has_error');

    if ($element->getProperty('autocomplete_route_name')) {
      $variables['is_autocomplete'] = TRUE;
    }

    $variables['is_image'] = FALSE;
    $variables['add_divider_after'] = TRUE;

    $element_type = $element->getProperty('type');
    $checkbox = $variables['is_checkbox'] = $element->isType('checkbox');
    $radio = $variables['is_radio'] = $element->isType('radio');

    // Determine if the form element should have the "form-group" class added.
    // Use an explicitly set property from the element or use its other
    // properties as the criteria to determine if it should be set.
    $variables['is_form_group'] = $element->getProperty('form_group', !$variables['is_checkbox'] && !$variables['is_radio'] && !$element->isType(['hidden', 'textarea']));

    // Add label_display and label variables to template.
    $display = $variables['label_display'] = $variables['title_display'] = $element->getProperty('title_display');

    $variables['label_display'] = 'after';

    switch ($element_type) {
      case 'password_confirm':
        $variables['add_divider_after'] = FALSE;
        break;

      case 'file':
      case 'managed_file':
        $variables['is_file'] = TRUE;
        $variables['add_divider_after'] = FALSE;
        break;

      case 'checkbox';
      case 'radio':
        $variables['add_divider_after'] = FALSE;
        $label = Element::create($variables['label']);
        $children = &$label->getProperty('children', '');
        $children .= $variables['children'];
        if ($label->getProperty('title_display') != 'none') {
          unset($variables['children']);
        }

        // Inform label if it is in checkbox/radio context.
        $label->setProperty('is_checkbox', $checkbox);
        $label->setProperty('is_radio', $radio);

        // Pass the label attributes to the label, if available.
        if ($element->hasProperty('label_attributes')) {
          $label->setAttributes($element->getProperty('label_attributes'));
        }
        break;

      case 'vertical_tabs':
        $variables['label_display'] = 'before';
        $variables['add_divider_after'] = FALSE;
        break;
    }

    // Remove the #field_prefix and #field_suffix values set in
    // template_preprocess_form_element(). These are handled at the input level.
    // @see \Drupal\materialize\Plugin\Preprocess\Input::preprocess().
    unset($variables['prefix']);
    unset($variables['suffix']);
  }

}
