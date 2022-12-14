<?php

namespace Drupal\materialize\Plugin\Preprocess;

use Drupal\materialize\Utility\Element;
use Drupal\materialize\Utility\Variables;
use Drupal\Component\Utility\NestedArray;

/**
 * Pre-processes variables for the "materialize_dropdown" theme hook.
 *
 * @ingroup plugins_preprocess
 *
 * @MaterializePreprocess("materialize_dropdown")
 */
class MaterializeDropdown extends PreprocessBase implements PreprocessInterface {

  /**
   * {@inheritdoc}
   */
  protected function preprocessVariables(Variables $variables) {
    $this->preprocessLinks($variables);

    $toggle = Element::create($variables->toggle);
    $toggle->setProperty('split', $variables->split);

    // Convert the items into a proper item list.
    $variables->items = [
      '#theme' => 'item_list__dropdown',
      '#alignment' => $variables->alignment,
      '#items' => $variables->items,
    ];

    // Ensure all attributes are proper objects.
    $this->preprocessAttributes();
  }

  /**
   * Preprocess links in the variables array to convert them from dropbuttons.
   *
   * @param \Drupal\materialize\Utility\Variables $variables
   *   A variables object.
   */
  protected function preprocessLinks(Variables $variables) {
    // Convert "dropbutton" theme suggestion variables.
    if (mb_strpos($variables->theme_hook_original, 'links__dropbutton') !== FALSE && !empty($variables->links)) {
      $operations = !!mb_strpos($variables->theme_hook_original, 'operations');

      // Normal dropbutton links are not actually render arrays, convert them.
      foreach ($variables->links as &$link) {
        if (isset($link['title']) && $link['url']) {
          // Preserve query parameters (if any)
          if (!empty($link['query'])) {
            $url_query = $link['url']->getOption('query') ?: [];
            $link['url']->setOption('query', NestedArray::mergeDeep($url_query , $link['query']));
          }

          // Build render array.
          $link = [
            '#type' => 'link',
            '#title' => $link['title'],
            '#url' => $link['url'],
          ];
        }
      }

      // Pop off the first link as the "toggle".
      $variables->toggle = array_shift($variables->links);
      $toggle = Element::create($variables->toggle);

      // Convert any toggle links to a proper button.
      if ($toggle->isType('link')) {
        $toggle->exchangeArray([
          '#type' => 'button',
          '#value' => $toggle->getProperty('title'),
          '#attributes' => [
            'data-url' => $toggle->getProperty('url')->toString(),
          ],
        ]);
        if ($operations) {
          // todo: handle operations
          // $toggle->setButtonSize('btn-xs');
        }
      }
      // Remove the dropbutton property.
      else {
        $toggle->unsetProperty('dropbutton');
      }

      $variables->items = array_values($variables->links);

      // Determine if toggle should be a split button.
      $variables->split = !!$variables->links;

      unset($variables->links);
    }
  }

}
