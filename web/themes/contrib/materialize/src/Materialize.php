<?php

namespace Drupal\materialize;

use Drupal\materialize\Plugin\AlterManager;
use Drupal\materialize\Plugin\FormManager;
use Drupal\materialize\Plugin\PreprocessManager;
use Drupal\materialize\Utility\Element;
use Drupal\materialize\Utility\Unicode;
use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Extension\ThemeHandlerInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * The primary class for the Drupal Materialize base theme.
 *
 * Provides many helper methods.
 *
 * @ingroup utility
 */
class Materialize {
  /**
   * Tag used to invalidate caches.
   *
   * @var string
   */
  const CACHE_TAG = 'theme_registry';

  /**
   * Append a callback.
   *
   * @var int
   */
  const CALLBACK_APPEND = 1;

  /**
   * Prepend a callback.
   *
   * @var int
   */
  const CALLBACK_PREPEND = 2;

  /**
   * Replace a callback or append it if not found.
   *
   * @var int
   */
  const CALLBACK_REPLACE_APPEND = 3;

  /**
   * Replace a callback or prepend it if not found.
   *
   * @var int
   */
  const CALLBACK_REPLACE_PREPEND = 4;

  /**
   * The current supported Materialize Framework version.
   *
   * @var string
   */
  const FRAMEWORK_VERSION = '0.98.0';

  /**
   * The Materialize Framework documentation site.
   *
   * @var string
   */
  const FRAMEWORK_HOMEPAGE = 'http://materializecss.com';

  /**
   * The Materialize Framework repository.
   *
   * @var string
   */
  const FRAMEWORK_REPOSITORY = 'https://github.com/dogfalo/materialize';

  /**
   * The project branch.
   *
   * @var string
   */
  const PROJECT_BRANCH = '8.x-1.x';

  /**
   * The Drupal Materialize documentation site.
   * todo: add doc. site.
   *
   * @var string
   */
  const PROJECT_DOCUMENTATION = 'http://materializecss.com';

  /**
   * The Drupal Materialize project page.
   *
   * @var string
   */
  const PROJECT_PAGE = 'https://www.drupal.org/project/materialize';

  /**
   * Adds a callback to an array.
   *
   * @param array $callbacks
   *   An array of callbacks to add the callback to, passed by reference.
   * @param array|string $callback
   *   The callback to add.
   * @param array|string $replace
   *   If specified, the callback will instead replace the specified value
   *   instead of being appended to the $callbacks array.
   * @param int $action
   *   Flag that determines how to add the callback to the array.
   *
   * @return bool
   *   TRUE if the callback was added, FALSE if $replace was specified but its
   *   callback could be found in the list of callbacks.
   */
  public static function addCallback(array &$callbacks, $callback, $replace = NULL, $action = Materialize::CALLBACK_APPEND) {
    // Replace a callback.
    if ($replace) {
      // Iterate through the callbacks.
      foreach ($callbacks as $key => $value) {
        // Convert each callback and match the string values.
        if (Unicode::convertCallback($value) === Unicode::convertCallback($replace)) {
          $callbacks[$key] = $callback;
          return TRUE;
        }
      }
      // No match found and action shouldn't append or prepend.
      if ($action !== self::CALLBACK_REPLACE_APPEND || $action !== self::CALLBACK_REPLACE_PREPEND) {
        return FALSE;
      }
    }

    // Append or prepend the callback.
    switch ($action) {
      case self::CALLBACK_APPEND:
      case self::CALLBACK_REPLACE_APPEND:
        $callbacks[] = $callback;
        return TRUE;

      case self::CALLBACK_PREPEND:
      case self::CALLBACK_REPLACE_PREPEND:
        array_unshift($callbacks, $callback);
        return TRUE;

      default:
        return FALSE;
    }
  }

  /**
   * Manages theme alter hooks as classes and allows sub-themes to sub-class.
   *
   * @param string $function
   *   The procedural function name of the alter (e.g. __FUNCTION__).
   * @param mixed $data
   *   The variable that was passed to the hook_TYPE_alter() implementation to
   *   be altered. The type of this variable depends on the value of the $type
   *   argument. For example, when altering a 'form', $data will be a structured
   *   array. When altering a 'profile', $data will be an object.
   * @param mixed $context1
   *   (optional) An additional variable that is passed by reference.
   * @param mixed $context2
   *   (optional) An additional variable that is passed by reference. If more
   *   context needs to be provided to implementations, then this should be an
   *   associative array as described above.
   */
  public static function alter($function, &$data, &$context1 = NULL, &$context2 = NULL) {
    static $theme;
    if (!isset($theme)) {
      $theme = self::getTheme();
    }

    // Immediately return if the active theme is not Materialize based.
    if (!$theme->isMaterialize()) {
      return;
    }

    // Extract the alter hook name.
    $hook = Unicode::extractHook($function, 'alter');

    // Handle form alters as a separate plugin.
    if (strpos($hook, 'form') === 0 && $context1 instanceof FormStateInterface) {
      $form_state = $context1;
      $form_id = $context2;

      // Due to a core bug that affects admin themes, we should not double
      // process the "system_theme_settings" form twice in the global
      // hook_form_alter() invocation.
      // @see https://drupal.org/node/943212
      if ($form_id === 'system_theme_settings') {
        return;
      }

      // Keep track of the form identifiers.
      $ids = [];

      // Get the build data.
      $build_info = $form_state->getBuildInfo();

      // Extract the base_form_id.
      $base_form_id = !empty($build_info['base_form_id']) ? $build_info['base_form_id'] : FALSE;
      if ($base_form_id) {
        $ids[] = $base_form_id;
      }

      // If there was no provided form identifier, extract it.
      if (!$form_id) {
        $form_id = !empty($build_info['form_id']) ? $build_info['form_id'] : Unicode::extractHook($function, 'alter', 'form');
      }
      if ($form_id) {
        $ids[] = $form_id;
      }

      // Retrieve a list of form definitions.
      $form_manager = new FormManager($theme);

      // Iterate over each form identifier and look for a possible plugin.
      foreach ($ids as $id) {
        /** @var \Drupal\materialize\Plugin\Form\FormInterface $form */
        if ($form_manager->hasDefinition($id) && ($form = $form_manager->createInstance($id, ['theme' => $theme]))) {
          $data['#submit'][] = [get_class($form), 'submitForm'];
          $data['#validate'][] = [get_class($form), 'validateForm'];
          $form->alterForm($data, $form_state, $form_id);
        }
      }
    }
    // Process hook alter normally.
    else {
      // Retrieve a list of alter definitions.
      $alter_manager = new AlterManager($theme);

      /** @var \Drupal\materialize\Plugin\Alter\AlterInterface $class */
      if ($alter_manager->hasDefinition($hook) && ($class = $alter_manager->createInstance($hook, ['theme' => $theme]))) {
        $class->alter($data, $context1, $context2);
      }
    }
  }

  /**
   * Returns a documentation search URL for a given query.
   *
   * Todo: update.
   *
   * @param string $query
   *   The query to search for.
   *
   * @return string
   *   The complete URL to the documentation site.
   */
  public static function apiSearchUrl($query = '') {
    return self::PROJECT_DOCUMENTATION . '/api/materialize/' . self::PROJECT_BRANCH . '/search/' . Html::escape($query);
  }

  /**
   * Returns the autoload fix include path.
   *
   * This method assists class based callbacks that normally do not work.
   *
   * If you notice that your class based callback is never invoked, you may try
   * using this helper method as an "include" or "file" for your callback, if
   * the callback metadata supports such an option.
   *
   * Depending on when or where a callback is invoked during a request, such as
   * an ajax or batch request, the theme handler may not yet be fully
   * initialized.
   *
   * Typically there is little that can be done about this "issue" from core.
   * It must balance the appropriate level that should be materializeped along
   * with common functionality. Cross-request class based callbacks are not
   * common in themes.
   *
   * When this file is included, it will attempt to jump start this process.
   *
   * Please keep in mind, that it is merely an attempt and does not guarantee
   * that it will actually work. If it does not appear to work, do not use it.
   *
   * @see \Drupal\Core\Extension\ThemeHandler::listInfo
   * @see \Drupal\Core\Extension\ThemeHandler::systemThemeList
   * @see system_list
   * @see system_register()
   * @see drupal_classloader_register()
   *
   * @return string
   *   The autoload fix include path, relative to Drupal root.
   */
  public static function autoloadFixInclude() {
    return static::getTheme('materialize')->getPath() . '/autoload-fix.php';
  }

  /**
   * Matches a Materialize class based on a string value.
   *
   * @param string|array $value
   *   The string to match against to determine the class. Passed by reference
   *   in case it is a render array that needs to be rendered and typecast.
   * @param string $default
   *   The default class to return if no match is found.
   *
   * @return string
   *   The Materialize class matched against the value of $haystack or $default
   *   if no match could be made.
   */
  public static function cssClassFromString(&$value, $default = '') {
    static $lang;
    if (!isset($lang)) {
      $lang = \Drupal::languageManager()->getCurrentLanguage()->getId();
    }

    $theme = static::getTheme();
    $texts = $theme->getCache('cssClassFromString', [$lang]);

    // Ensure it's a string value that was passed.
    $string = static::toString($value);

    if ($texts->isEmpty()) {
      $data = [
        // Text that match these specific strings are checked first.
        'matches' => [
          // Primary class.
          t('Download feature')->render()   => 'primary',

          // Success class.
          t('Add effect')->render()         => 'success',
          t('Add and configure')->render()  => 'success',
          t('Save configuration')->render() => 'success',
          t('Install and set as default')->render() => 'success',

          // Info class.
          t('Save and add')->render()       => 'info',
          t('Add another item')->render()   => 'info',
          t('Update style')->render()       => 'info',
        ],

        // Text containing these words anywhere in the string are checked last.
        'contains' => [
          // Primary class.
          t('Confirm')->render()            => 'primary',
          t('Filter')->render()             => 'primary',
          t('Submit')->render()             => 'primary',
          t('Search')->render()             => 'primary',
          t('Settings')->render()           => 'primary',
          t('Log in')->render()             => 'primary',

          // Danger class.
          t('Delete')->render()             => 'danger',
          t('Remove')->render()             => 'danger',
          t('Uninstall')->render()          => 'danger',

          // Success class.
          t('Add')->render()                => 'success',
          t('Create')->render()             => 'success',
          t('Install')->render()            => 'success',
          t('Save')->render()               => 'success',
          t('Write')->render()              => 'success',

          // Warning class.
          t('Export')->render()             => 'warning',
          t('Import')->render()             => 'warning',
          t('Restore')->render()            => 'warning',
          t('Rebuild')->render()            => 'warning',

          // Info class.
          t('Apply')->render()              => 'info',
          t('Update')->render()             => 'info',
        ],
      ];

      // Allow sub-themes to alter this array of patterns.
      /** @var \Drupal\Core\Theme\ThemeManager $theme_manager */
      $theme_manager = \Drupal::service('theme.manager');
      $theme_manager->alter('materialize_colorize_text', $data);

      $texts->setMultiple($data);
    }

    // Iterate over the array.
    foreach ($texts as $pattern => $strings) {
      foreach ($strings as $text => $class) {
        switch ($pattern) {
          case 'matches':
            if ($string === $text) {
              return $class;
            }
            break;

          case 'contains':
            if (strpos(mb_strtolower($string), mb_strtolower($text)) !== FALSE) {
              return $class;
            }
            break;
        }
      }
    }

    // Return the default if nothing was matched.
    return $default;
  }

  /**
   * Logs and displays a warning about a deprecated function/method being used.
   */
  public static function deprecated() {
    // Log backtrace.
    $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
    \Drupal::logger('materialize')->warning('<pre><code>' . print_r($backtrace, TRUE) . '</code></pre>');

    if (!self::getTheme()->getSetting('suppress_deprecated_warnings')) {
      return;
    }

    // Extrapolate the caller.
    $caller = $backtrace[1];
    $class = '';
    if (isset($caller['class'])) {
      $parts = explode('\\', $caller['class']);
      $class = array_pop($parts) . '::';
    }
    \Drupal::messenger()->addWarning(t('The following function(s) or method(s) have been deprecated, please check the logs for a more detailed backtrace on where these are being invoked. Click on the function or method link to search the documentation site for a possible replacement or solution.'));
    \Drupal::messenger()->addWarning(t('<a href=":url" target="_blank">@title</a>.', [
      ':url' => self::apiSearchUrl($class . $caller['function']),
      '@title' => ($class ? $caller['class'] . $caller['type'] : '') . $caller['function'] . '()',
    ]));
  }

  /**
   * Provides additional variables to be used in elements and templates.
   *
   * @return array
   *   An associative array containing key/default value pairs.
   */
  public static function extraVariables() {
    return [
      // @see https://drupal.org/node/2035055
      'context' => [],

      // @see https://drupal.org/node/2219965
      'icon' => NULL,
      'icon_position' => 'before',
      'icon_only' => FALSE,
    ];
  }

  /**
   * Retrieves a theme instance of \Drupal\materialize.
   *
   * @param string $name
   *   The machine name of a theme. If omitted, the active theme will be used.
   * @param \Drupal\Core\Extension\ThemeHandlerInterface $theme_handler
   *   The theme handler object.
   *
   * @return \Drupal\materialize\Theme
   *   A theme object.
   */
  public static function getTheme($name = NULL, ThemeHandlerInterface $theme_handler = NULL) {
    // Immediately return if theme passed is already instantiated.
    if ($name instanceof Theme) {
      return $name;
    }

    static $themes = [];
    static $active_theme;
    if (!isset($active_theme)) {
      $active_theme = \Drupal::theme()->getActiveTheme()->getName();
    }
    if (!isset($name)) {
      $name = $active_theme;
    }

    if (!isset($theme_handler)) {
      $theme_handler = self::getThemeHandler();
    }

    if (!isset($themes[$name])) {
      $themes[$name] = new Theme($theme_handler->getTheme($name), $theme_handler);
    }

    return $themes[$name];
  }

  /**
   * Retrieves the theme handler instance.
   *
   * @return \Drupal\Core\Extension\ThemeHandlerInterface
   *   The theme handler instance.
   */
  public static function getThemeHandler() {
    static $theme_handler;
    if (!isset($theme_handler)) {
      $theme_handler = \Drupal::service('theme_handler');
    }
    return $theme_handler;
  }

  /**
   * Returns the theme hook definition information.
   *
   * This base-theme's custom theme hook implementations. Never define "path"
   * as this is automatically detected and added.
   *
   * @see \Drupal\materialize\Plugin\Alter\ThemeRegistry::alter()
   * @see materialize_theme_registry_alter()
   * @see materialize_theme()
   * @see hook_theme()
   */
  public static function getThemeHooks() {
    $hooks = [];
    $hooks['materialize_dropdown'] = [
      'variables' => [
        'alignment' => NULL,
        'attributes' => [],
        'items' => [],
        'split' => FALSE,
        'toggle' => NULL,
      ],
    ];

    return $hooks;
  }

  /**
   * todo: remove? alter?
   * Returns a specific Materialize Glyphicon.
   *
   * @param string $name
   *   The icon name, minus the "glyphicon-" prefix.
   * @param array $default
   *   (Optional) The default render array to use if $name is not available.
   *
   * @return array
   *   The render containing the icon defined by $name, $default value if
   *   icon does not exist or returns NULL if no icon could be rendered.
   */
  public static function glyphicon($name, array $default = []) {
    $icon = [];

    // Ensure the icon specified is a valid Materialize Glyphicon.
    // @todo Supply a specific version to _materialize_glyphicons() when Icon API
    // supports versioning.
    if (self::getTheme()->hasGlyphicons() && in_array($name, self::glyphicons())) {
      // Attempt to use the Icon API module, if enabled and it generates output.
      if (\Drupal::moduleHandler()->moduleExists('icon')) {
        $icon = [
          '#type' => 'icon',
          '#bundle' => 'materialize',
          '#icon' => 'glyphicon-' . $name,
        ];
      }
      else {
        $icon = [
          '#type' => 'html_tag',
          '#tag' => 'span',
          '#value' => '',
          '#attributes' => [
            'class' => ['icon', 'glyphicon', 'glyphicon-' . $name],
            'aria-hidden' => 'true',
          ],
        ];
      }
    }

    return $icon ?: $default;
  }

  /**
   * Matches a Materialize icon based on a string value.
   *
   * @param string $value
   *   The string to match against to determine the icon. Passed by reference
   *   in case it is a render array that needs to be rendered and typecast.
   * @param array $default
   *   The default render array to return if no match is found.
   *
   * @return string
   *   The Materialize icon matched against the value of $haystack or $default
   *   if no match could be made.
   */
  public static function iconFromString(&$value, array $default = []) {
    static $lang;
    if (!isset($lang)) {
      $lang = \Drupal::languageManager()->getCurrentLanguage()->getId();
    }

    $theme = static::getTheme();
    $texts = $theme->getCache('iconFromString', [$lang]);

    // Ensure it's a string value that was passed.
    $string = static::toString($value);

    if ($texts->isEmpty()) {
      $data = [
        // Text that match these specific strings are checked first.
        'matches' => [],

        // todo: add context.
        // Text containing these words anywhere in the string are checked last.
        'contains' => [
          t('Manage')->render()     => 'settings',
          t('Configure')->render()  => 'settings',
          t('Settings')->render()   => 'settings',
          t('Download')->render()   => 'file_download',
          t('Export')->render()     => 'arrow_forward',
          t('Filter')->render()     => 'filter_list',
          t('Import')->render()     => 'arrow_back',
          t('Save')->render()       => 'send',
          t('Update')->render()     => 'send',
          t('Edit')->render()       => 'mode_edit',
          t('Uninstall')->render()  => 'delete_forever',
          t('Install')->render()    => 'add',
          t('Write')->render()      => 'create',
          t('Cancel')->render()     => 'clear',
          t('Delete')->render()     => 'delete',
          t('Remove')->render()     => 'remove_circle_outline',
          t('Search')->render()     => 'search',
          t('Upload')->render()     => 'file_upload',
          t('Preview')->render()    => 'visibility',
          t('Log in')->render()     => 'check',
          t('Close')->render()      => 'close',
        ],
      ];

      // Allow sub-themes to alter this array of patterns.
      /** @var \Drupal\Core\Theme\ThemeManager $theme_manager */
      $theme_manager = \Drupal::service('theme.manager');
      $theme_manager->alter('materialize_iconize_text', $data);

      $texts->setMultiple($data);
    }

    // Iterate over the array.
    // todo: The result should be a simple icon CSS class.

    // foreach ($texts as $pattern => $strings) {
    //   foreach ($strings as $text => $icon) {
    //     switch ($pattern) {
    //       case 'matches':
    //         if ($string === $text) {
    //           return self::glyphicon($icon, $default);
    //         }
    //         break;
    //
    //       case 'contains':
    //         if (strpos(Unicode::strtolower($string), Unicode::strtolower($text)) !== FALSE) {
    //           return self::glyphicon($icon, $default);
    //         }
    //         break;
    //     }
    //   }
    // }

    // Return a default icon if nothing was matched.
    return $default;
  }

  /**
   * // todo: remove? alter?
   * Returns a list of available Materialize Framework Glyphicons.
   *
   * @param string $version
   *   The specific version of glyphicons to return. If not set, the latest
   *   materialize_VERSION will be used.
   *
   * @return array
   *   An associative array of icons keyed by their classes.
   */
  public static function glyphicons($version = NULL) {
    static $versions;
    if (!isset($versions)) {
      $versions = [];
      $versions['3.0.0'] = [
        // Class => Name.
        'glyphicon-adjust' => 'adjust',
        'glyphicon-align-center' => 'align-center',
        'glyphicon-align-justify' => 'align-justify',
        'glyphicon-align-left' => 'align-left',
        'glyphicon-align-right' => 'align-right',
        'glyphicon-arrow-down' => 'arrow-down',
        'glyphicon-arrow-left' => 'arrow-left',
        'glyphicon-arrow-right' => 'arrow-right',
        'glyphicon-arrow-up' => 'arrow-up',
        'glyphicon-asterisk' => 'asterisk',
        'glyphicon-backward' => 'backward',
        'glyphicon-ban-circle' => 'ban-circle',
        'glyphicon-barcode' => 'barcode',
        'glyphicon-bell' => 'bell',
        'glyphicon-bold' => 'bold',
        'glyphicon-book' => 'book',
        'glyphicon-bookmark' => 'bookmark',
        'glyphicon-briefcase' => 'briefcase',
        'glyphicon-bullhorn' => 'bullhorn',
        'glyphicon-calendar' => 'calendar',
        'glyphicon-camera' => 'camera',
        'glyphicon-certificate' => 'certificate',
        'glyphicon-check' => 'check',
        'glyphicon-chevron-down' => 'chevron-down',
        'glyphicon-chevron-left' => 'chevron-left',
        'glyphicon-chevron-right' => 'chevron-right',
        'glyphicon-chevron-up' => 'chevron-up',
        'glyphicon-circle-arrow-down' => 'circle-arrow-down',
        'glyphicon-circle-arrow-left' => 'circle-arrow-left',
        'glyphicon-circle-arrow-right' => 'circle-arrow-right',
        'glyphicon-circle-arrow-up' => 'circle-arrow-up',
        'glyphicon-cloud' => 'cloud',
        'glyphicon-cloud-download' => 'cloud-download',
        'glyphicon-cloud-upload' => 'cloud-upload',
        'glyphicon-cog' => 'cog',
        'glyphicon-collapse-down' => 'collapse-down',
        'glyphicon-collapse-up' => 'collapse-up',
        'glyphicon-comment' => 'comment',
        'glyphicon-compressed' => 'compressed',
        'glyphicon-copyright-mark' => 'copyright-mark',
        'glyphicon-credit-card' => 'credit-card',
        'glyphicon-cutlery' => 'cutlery',
        'glyphicon-dashboard' => 'dashboard',
        'glyphicon-download' => 'download',
        'glyphicon-download-alt' => 'download-alt',
        'glyphicon-earphone' => 'earphone',
        'glyphicon-edit' => 'edit',
        'glyphicon-eject' => 'eject',
        'glyphicon-envelope' => 'envelope',
        'glyphicon-euro' => 'euro',
        'glyphicon-exclamation-sign' => 'exclamation-sign',
        'glyphicon-expand' => 'expand',
        'glyphicon-export' => 'export',
        'glyphicon-eye-close' => 'eye-close',
        'glyphicon-eye-open' => 'eye-open',
        'glyphicon-facetime-video' => 'facetime-video',
        'glyphicon-fast-backward' => 'fast-backward',
        'glyphicon-fast-forward' => 'fast-forward',
        'glyphicon-file' => 'file',
        'glyphicon-film' => 'film',
        'glyphicon-filter' => 'filter',
        'glyphicon-fire' => 'fire',
        'glyphicon-flag' => 'flag',
        'glyphicon-flash' => 'flash',
        'glyphicon-floppy-disk' => 'floppy-disk',
        'glyphicon-floppy-open' => 'floppy-open',
        'glyphicon-floppy-remove' => 'floppy-remove',
        'glyphicon-floppy-save' => 'floppy-save',
        'glyphicon-floppy-saved' => 'floppy-saved',
        'glyphicon-folder-close' => 'folder-close',
        'glyphicon-folder-open' => 'folder-open',
        'glyphicon-font' => 'font',
        'glyphicon-forward' => 'forward',
        'glyphicon-fullscreen' => 'fullscreen',
        'glyphicon-gbp' => 'gbp',
        'glyphicon-gift' => 'gift',
        'glyphicon-glass' => 'glass',
        'glyphicon-globe' => 'globe',
        'glyphicon-hand-down' => 'hand-down',
        'glyphicon-hand-left' => 'hand-left',
        'glyphicon-hand-right' => 'hand-right',
        'glyphicon-hand-up' => 'hand-up',
        'glyphicon-hd-video' => 'hd-video',
        'glyphicon-hdd' => 'hdd',
        'glyphicon-header' => 'header',
        'glyphicon-headphones' => 'headphones',
        'glyphicon-heart' => 'heart',
        'glyphicon-heart-empty' => 'heart-empty',
        'glyphicon-home' => 'home',
        'glyphicon-import' => 'import',
        'glyphicon-inbox' => 'inbox',
        'glyphicon-indent-left' => 'indent-left',
        'glyphicon-indent-right' => 'indent-right',
        'glyphicon-info-sign' => 'info-sign',
        'glyphicon-italic' => 'italic',
        'glyphicon-leaf' => 'leaf',
        'glyphicon-link' => 'link',
        'glyphicon-list' => 'list',
        'glyphicon-list-alt' => 'list-alt',
        'glyphicon-lock' => 'lock',
        'glyphicon-log-in' => 'log-in',
        'glyphicon-log-out' => 'log-out',
        'glyphicon-magnet' => 'magnet',
        'glyphicon-map-marker' => 'map-marker',
        'glyphicon-minus' => 'minus',
        'glyphicon-minus-sign' => 'minus-sign',
        'glyphicon-move' => 'move',
        'glyphicon-music' => 'music',
        'glyphicon-new-window' => 'new-window',
        'glyphicon-off' => 'off',
        'glyphicon-ok' => 'ok',
        'glyphicon-ok-circle' => 'ok-circle',
        'glyphicon-ok-sign' => 'ok-sign',
        'glyphicon-open' => 'open',
        'glyphicon-paperclip' => 'paperclip',
        'glyphicon-pause' => 'pause',
        'glyphicon-pencil' => 'pencil',
        'glyphicon-phone' => 'phone',
        'glyphicon-phone-alt' => 'phone-alt',
        'glyphicon-picture' => 'picture',
        'glyphicon-plane' => 'plane',
        'glyphicon-play' => 'play',
        'glyphicon-play-circle' => 'play-circle',
        'glyphicon-plus' => 'plus',
        'glyphicon-plus-sign' => 'plus-sign',
        'glyphicon-print' => 'print',
        'glyphicon-pushpin' => 'pushpin',
        'glyphicon-qrcode' => 'qrcode',
        'glyphicon-question-sign' => 'question-sign',
        'glyphicon-random' => 'random',
        'glyphicon-record' => 'record',
        'glyphicon-refresh' => 'refresh',
        'glyphicon-registration-mark' => 'registration-mark',
        'glyphicon-remove' => 'remove',
        'glyphicon-remove-circle' => 'remove-circle',
        'glyphicon-remove-sign' => 'remove-sign',
        'glyphicon-repeat' => 'repeat',
        'glyphicon-resize-full' => 'resize-full',
        'glyphicon-resize-horizontal' => 'resize-horizontal',
        'glyphicon-resize-small' => 'resize-small',
        'glyphicon-resize-vertical' => 'resize-vertical',
        'glyphicon-retweet' => 'retweet',
        'glyphicon-road' => 'road',
        'glyphicon-save' => 'save',
        'glyphicon-saved' => 'saved',
        'glyphicon-screenshot' => 'screenshot',
        'glyphicon-sd-video' => 'sd-video',
        'glyphicon-search' => 'search',
        'glyphicon-send' => 'send',
        'glyphicon-share' => 'share',
        'glyphicon-share-alt' => 'share-alt',
        'glyphicon-shopping-cart' => 'shopping-cart',
        'glyphicon-signal' => 'signal',
        'glyphicon-sort' => 'sort',
        'glyphicon-sort-by-alphabet' => 'sort-by-alphabet',
        'glyphicon-sort-by-alphabet-alt' => 'sort-by-alphabet-alt',
        'glyphicon-sort-by-attributes' => 'sort-by-attributes',
        'glyphicon-sort-by-attributes-alt' => 'sort-by-attributes-alt',
        'glyphicon-sort-by-order' => 'sort-by-order',
        'glyphicon-sort-by-order-alt' => 'sort-by-order-alt',
        'glyphicon-sound-5-1' => 'sound-5-1',
        'glyphicon-sound-6-1' => 'sound-6-1',
        'glyphicon-sound-7-1' => 'sound-7-1',
        'glyphicon-sound-dolby' => 'sound-dolby',
        'glyphicon-sound-stereo' => 'sound-stereo',
        'glyphicon-star' => 'star',
        'glyphicon-star-empty' => 'star-empty',
        'glyphicon-stats' => 'stats',
        'glyphicon-step-backward' => 'step-backward',
        'glyphicon-step-forward' => 'step-forward',
        'glyphicon-stop' => 'stop',
        'glyphicon-subtitles' => 'subtitles',
        'glyphicon-tag' => 'tag',
        'glyphicon-tags' => 'tags',
        'glyphicon-tasks' => 'tasks',
        'glyphicon-text-height' => 'text-height',
        'glyphicon-text-width' => 'text-width',
        'glyphicon-th' => 'th',
        'glyphicon-th-large' => 'th-large',
        'glyphicon-th-list' => 'th-list',
        'glyphicon-thumbs-down' => 'thumbs-down',
        'glyphicon-thumbs-up' => 'thumbs-up',
        'glyphicon-time' => 'time',
        'glyphicon-tint' => 'tint',
        'glyphicon-tower' => 'tower',
        'glyphicon-transfer' => 'transfer',
        'glyphicon-trash' => 'trash',
        'glyphicon-tree-conifer' => 'tree-conifer',
        'glyphicon-tree-deciduous' => 'tree-deciduous',
        'glyphicon-unchecked' => 'unchecked',
        'glyphicon-upload' => 'upload',
        'glyphicon-usd' => 'usd',
        'glyphicon-user' => 'user',
        'glyphicon-volume-down' => 'volume-down',
        'glyphicon-volume-off' => 'volume-off',
        'glyphicon-volume-up' => 'volume-up',
        'glyphicon-warning-sign' => 'warning-sign',
        'glyphicon-wrench' => 'wrench',
        'glyphicon-zoom-in' => 'zoom-in',
        'glyphicon-zoom-out' => 'zoom-out',
      ];
      $versions['3.0.1'] = $versions['3.0.0'];
      $versions['3.0.2'] = $versions['3.0.1'];
      $versions['3.0.3'] = $versions['3.0.2'];
      $versions['3.1.0'] = $versions['3.0.3'];
      $versions['3.1.1'] = $versions['3.1.0'];
      $versions['3.2.0'] = $versions['3.1.1'];
      $versions['3.3.0'] = array_merge($versions['3.2.0'], [
        'glyphicon-eur' => 'eur',
      ]);
      $versions['3.3.1'] = $versions['3.3.0'];
      $versions['3.3.2'] = array_merge($versions['3.3.1'], [
        'glyphicon-alert' => 'alert',
        'glyphicon-apple' => 'apple',
        'glyphicon-baby-formula' => 'baby-formula',
        'glyphicon-bed' => 'bed',
        'glyphicon-bishop' => 'bishop',
        'glyphicon-bitcoin' => 'bitcoin',
        'glyphicon-blackboard' => 'blackboard',
        'glyphicon-cd' => 'cd',
        'glyphicon-console' => 'console',
        'glyphicon-copy' => 'copy',
        'glyphicon-duplicate' => 'duplicate',
        'glyphicon-education' => 'education',
        'glyphicon-equalizer' => 'equalizer',
        'glyphicon-erase' => 'erase',
        'glyphicon-grain' => 'grain',
        'glyphicon-hourglass' => 'hourglass',
        'glyphicon-ice-lolly' => 'ice-lolly',
        'glyphicon-ice-lolly-tasted' => 'ice-lolly-tasted',
        'glyphicon-king' => 'king',
        'glyphicon-knight' => 'knight',
        'glyphicon-lamp' => 'lamp',
        'glyphicon-level-up' => 'level-up',
        'glyphicon-menu-down' => 'menu-down',
        'glyphicon-menu-hamburger' => 'menu-hamburger',
        'glyphicon-menu-left' => 'menu-left',
        'glyphicon-menu-right' => 'menu-right',
        'glyphicon-menu-up' => 'menu-up',
        'glyphicon-modal-window' => 'modal-window',
        'glyphicon-object-align-bottom' => 'object-align-bottom',
        'glyphicon-object-align-horizontal' => 'object-align-horizontal',
        'glyphicon-object-align-left' => 'object-align-left',
        'glyphicon-object-align-right' => 'object-align-right',
        'glyphicon-object-align-top' => 'object-align-top',
        'glyphicon-object-align-vertical' => 'object-align-vertical',
        'glyphicon-oil' => 'oil',
        'glyphicon-open-file' => 'open-file',
        'glyphicon-option-horizontal' => 'option-horizontal',
        'glyphicon-option-vertical' => 'option-vertical',
        'glyphicon-paste' => 'paste',
        'glyphicon-pawn' => 'pawn',
        'glyphicon-piggy-bank' => 'piggy-bank',
        'glyphicon-queen' => 'queen',
        'glyphicon-ruble' => 'ruble',
        'glyphicon-save-file' => 'save-file',
        'glyphicon-scale' => 'scale',
        'glyphicon-scissors' => 'scissors',
        'glyphicon-subscript' => 'subscript',
        'glyphicon-sunglasses' => 'sunglasses',
        'glyphicon-superscript' => 'superscript',
        'glyphicon-tent' => 'tent',
        'glyphicon-text-background' => 'text-background',
        'glyphicon-text-color' => 'text-color',
        'glyphicon-text-size' => 'text-size',
        'glyphicon-triangle-bottom' => 'triangle-bottom',
        'glyphicon-triangle-left' => 'triangle-left',
        'glyphicon-triangle-right' => 'triangle-right',
        'glyphicon-triangle-top' => 'triangle-top',
        'glyphicon-yen' => 'yen',
      ]);
      $versions['3.3.4'] = array_merge($versions['3.3.2'], [
        'glyphicon-btc' => 'btc',
        'glyphicon-jpy' => 'jpy',
        'glyphicon-rub' => 'rub',
        'glyphicon-xbt' => 'xbt',
      ]);
      $versions['3.3.5'] = $versions['3.3.4'];
    }

    // Return a specific versions icon set.
    if (isset($version) && isset($versions[$version])) {
      return $versions[$version];
    }

    // Return the latest version.
    return $versions[self::FRAMEWORK_VERSION];
  }

  /**
   * Determines if the "cache_context.url.path.is_front" service exists.
   *
   * @return bool
   *   TRUE or FALSE
   *
   * @see \Drupal\materialize\Materialize::isFront
   * @see \Drupal\materialize\Materialize::preprocess
   * @see https://www.drupal.org/node/2829588
   */
  public static function hasIsFrontCacheContext() {
    static $has_is_front_cache_context;
    if (!isset($has_is_front_cache_context)) {
      $has_is_front_cache_context = \Drupal::getContainer()->has('cache_context.url.path.is_front');
    }
    return $has_is_front_cache_context;
  }

  /**
   * Initializes the active theme.
   */
  final public static function initialize() {
    static $initialized = FALSE;
    if (!$initialized) {
      // Initialize the active theme.
      $active_theme = self::getTheme();

      // todo: Include deprecated functions.
      /*foreach ($active_theme->getAncestry() as $ancestor) {
        if ($ancestor->getSetting('include_deprecated')) {
          $files = $ancestor->fileScan('/^deprecated\.php$/');
          if ($file = reset($files)) {
            $ancestor->includeOnce($file->uri, FALSE);
          }
        }
      }*/

      $initialized = TRUE;
    }
  }

  /**
   * Determines if the current path is the "front" page.
   *
   * *Note:* This method will not return `TRUE` if there is not a proper
   * "cache_context.url.path.is_front" service defined.
   *
   * *Note:* If using this method in preprocess/render array logic, the proper
   * #cache context must also be defined:
   *
   * ```php
   * $variables['#cache']['contexts'][] = 'url.path.is_front';
   * ```
   *
   * @return bool
   *   TRUE or FALSE
   *
   * @see \Drupal\materialize\Materialize::hasIsFrontCacheContext
   * @see \Drupal\materialize\Materialize::preprocess
   * @see https://www.drupal.org/node/2829588
   */
  public static function isFront() {
    static $is_front;
    if (!isset($is_front)) {
      try {
        $is_front = static::hasIsFrontCacheContext() ? \Drupal::service('path.matcher')->isFrontPage() : FALSE;
      }
      catch (\Exception $e) {
        $is_front = FALSE;
      }
    }
    return $is_front;
  }

  /**
   * Preprocess theme hook variables.
   *
   * @param array $variables
   *   The variables array, passed by reference.
   * @param string $hook
   *   The name of the theme hook.
   * @param array $info
   *   The theme hook info.
   */
  public static function preprocess(array &$variables, $hook, array $info) {
    static $theme;
    if (!isset($theme)) {
      $theme = self::getTheme();
    }
    static $preprocess_manager;
    if (!isset($preprocess_manager)) {
      $preprocess_manager = new PreprocessManager($theme);
    }

    // Adds a global "is_front" variable back to all templates.
    // @see https://www.drupal.org/node/2829585
    if (!isset($variables['is_front'])) {
      $variables['is_front'] = static::isFront();
      if (static::hasIsFrontCacheContext()) {
        $variables['#cache']['contexts'][] = 'url.path.is_front';
      }
    }

    // Ensure that any default theme hook variables exist. Due to how theme
    // hook suggestion alters work, the variables provided are from the
    // original theme hook, not the suggestion.
    if (isset($info['variables'])) {
      $variables = NestedArray::mergeDeepArray([$info['variables'], $variables], TRUE);
    }

    // Add extra variables to all theme hooks.
    foreach (Materialize::extraVariables() as $key => $value) {
      if (!isset($variables[$key])) {
        $variables[$key] = $value;
      }
    }

    // Add active theme context.
    // @see https://www.drupal.org/node/2630870
    if (!isset($variables['theme'])) {
      $variables['theme'] = $theme->getInfo();
      $variables['theme']['dev'] = $theme->isDev();
      $variables['theme']['livereload'] = $theme->livereloadUrl();
      $variables['theme']['name'] = $theme->getName();
      $variables['theme']['path'] = $theme->getPath();
      $variables['theme']['title'] = $theme->getTitle();
      $variables['theme']['settings'] = $theme->settings()->get();
      $variables['theme']['has_glyphicons'] = $theme->hasGlyphicons();
      $variables['theme']['query_string'] = \Drupal::getContainer()->get('state')->get('system.css_js_query_string') ?: '0';
    }

    // Invoke necessary preprocess plugin.
    if (isset($info['materialize preprocess'])) {
      if ($preprocess_manager->hasDefinition($info['materialize preprocess'])) {
        $class = $preprocess_manager->createInstance($info['materialize preprocess'], ['theme' => $theme]);
        /** @var \Drupal\materialize\Plugin\Preprocess\PreprocessInterface $class */
        $class->preprocess($variables, $hook, $info);
      }
    }
  }

  /**
   * Ensures a value is typecast to a string, rendering an array if necessary.
   *
   * @param string|array $value
   *   The value to typecast, passed by reference.
   *
   * @return string
   *   The typecast string value.
   */
  public static function toString(&$value) {
    return (string) (Element::isRenderArray($value) ? Element::create($value)->renderPlain() : $value);
  }

}
