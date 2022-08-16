<?php

namespace Drupal\materialize\Plugin\Preprocess;

use Drupal\materialize\Annotation\MaterializePreprocess;
use Drupal\materialize\Utility\Variables;
use Drupal\Core\Template\Attribute;

/**
 * Pre-processes variables for the "views_mini_pager" theme hook.
 *
 * @ingroup plugins_preprocess
 *
 * @MaterializePreprocess("views_mini_pager")
 */
class ViewsMiniPager extends PreprocessBase implements PreprocessInterface {

  /**
   * {@inheritdoc}
   */
  public function preprocessVariables(Variables $variables) {
    if ($variables['items']) {
      foreach ($variables['items'] as $name => $item) {
        if ('current' != $name) {
          if (empty($item['attributes'])) {
            $variables['items'][$name]['attributes'] = new Attribute(['class' => ['waves-effect']]);
          }
          else {
            $variables['items'][$name]['attributes']->addClass('waves-effect');
          }
        }
      }
    }
  }

}
