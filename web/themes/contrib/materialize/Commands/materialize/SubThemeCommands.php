<?php

namespace Drush\Commands\materialize;

use Drush\Commands\DrushCommands;
use DrupalCodeGenerator\Utils;

/**
 * A Drush command file for Materialize theme.
 *
 * In addition to this file, you need a drush.services.yml
 * in root of your module, and a composer.json file that provides the name
 * of the services file to use.
 *
 * See these files for an example of injecting Drupal services:
 *   - http://cgit.drupalcode.org/devel/tree/src/Commands/DevelCommands.php
 *   - http://cgit.drupalcode.org/devel/tree/drush.services.yml
 */
class SubThemeCommands extends DrushCommands {

  /**
   * Create a sub-theme based on Materialize.
   *
   * @param string $machine_name
   *   [optional] A machine-readable name for your theme.
   * @param string $name
   *   A name for your theme.
   * @param array $options
   *   An associative array of options whose values come from cli.
   *
   * @option name
   *   A name for your theme.
   * @option machine-name
   *   [a-z, 0-9, _] A machine-readable name for your theme.
   *
   * @option path
   *   The path where your theme will be created. Defaults to: themes/
   *
   * @option description
   *   A description of your theme.
   *
   * @usage drush theme:materialize:subtheme "Amazing name"
   *   Create a sub-theme, using the default options.
   *
   * @usage drush theme:materialize:subtheme momg_amazing "Amazing name"
   *   Create a sub-theme with a specific machine name.
   *
   * @usage drush theme:materialize:subtheme "Amazing name" --path=sites/default/themes --description="So amazing."
   *   Create a sub-theme in the specified directory with a custom description.
   *
   * @command theme:materialize:subtheme
   *   The command name.
   *
   * @aliases mst
   *
   * todo: refactor this command.
   */
  public function materializeSubtheme($machine_name, $name, array $options = [
    'name' => NULL,
    'machine-name' => NULL,
    'path' => NULL,
    'description' => NULL,
  ]) {

    if (!empty($options['machine-name'])) {
      $machine_name = $options['machine-name'];
      $this->output()->writeln(dt('Set machine-name to: !machine. ', ['!machine' => $options['machine-name']]));
    }

    if (!empty($options['name'])) {
      $name = $options['name'];
      $this->output()->writeln(dt('Set name to: !name', ['!name' => $options['output']]));
    }

    // Clean up the machine name.
    $machine_name = str_replace(' ', '_', strtolower($machine_name));
    $search = [
      // Remove characters not valid in function names.
      '/[^a-z0-9_]/',
      // Functions must begin with an alpha character.
      '/^[^a-z]+/',
    ];
    $machine_name = preg_replace($search, '', $machine_name);

    $this->output()->writeln(dt('Generate !machine-name theme named "!name"',
      [
        '!machine-name' => $machine_name,
        '!name' => $name,
      ]
    ));

    // Determine the path to the new sub-theme.
    $sub_theme_path = 'themes';
    if ($path = $options['path']) {
      // todo: where is trim path?
      $sub_theme_path = drush_trim_path($path);
    }
    $drupal_root = Drush::bootstrapManager()->getRoot();

    $sub_theme_path = Utils::normalizePath($drupal_root . '/' . $sub_theme_path . '/' . $machine_name);

    $this->output()->writeln(dt('Theme path is "!path"',
      [
        '!path' => $sub_theme_path,
      ]
    ));

    // Ensure the STARTERKIT directory exists.
    $starterkit_path = Utils::normalizePath($drupal_root . '/' . drupal_get_path('theme', 'materialize') . '/STARTERKIT');
    if (!is_dir($starterkit_path)) {
      return drush_set_error('MATERIALIZE_STARTERKIT_NOT_FOUND',
        dt('The STARTERKIT directory was not found in "!directory"',
          ['!directory' => dirname($starterkit_path)]
        ));
      // Allow localize.drupal.org to pick up the string to translate.
      if (FALSE) {
        t('The STARTERKIT directory was not found in "!directory"',
          ['!directory' => dirname($starterkit_path)]);
      }
    }

    $this->output()->writeln(dt('Copying files from starter kit…'));
    // Allow localize.drupal.org to pick up the string to translate.
    if (FALSE) {
      t('Copying files from starter kit…');
    }

    // Make a fresh copy of the original starter kit.
    if (!drush_op('drush_copy_dir', $starterkit_path, $sub_theme_path)) {
      // The drush_copy_dir errors are fatal errors for our materialize
      // drush command.
      return FALSE;
    }

    $info_strings = [
      ": 'Materialize Sub-theme Starter Kit'" => ': ' . $name,
      '# core: 8.x' => 'core: 8.x',
      "core: '8.x'\n" => '',
      "project: 'materialize'\n" => '',
      "hidden: true\n" => '',
    ];

    if ($description = $options['description']) {
      $info_strings['Uses the Materialize framework LESS/SASS source files and must be compiled (not for beginners).']
        = $description . ' (Materialize sub-theme)';
    }

    $info_regexs = [
      ['pattern' => '/\# Information added by Drupal\.org packaging script on [\d-]+\n/', 'replacement' => ''],
      ['pattern' => "/version: '[^']+'\n/", 'replacement' => ''],
      ['pattern' => '/datestamp: \d+\n/', 'replacement' => ''],
    ];

    // todo: use more advanced solution.
    drush_op('materialize_helper_file_replace', $sub_theme_path . '/STARTERKIT.info.yml', $info_strings, $info_regexs);

    // ***************************************************
    // Replace STARTERKIT in file names and contents.
    // ***************************************************.
    $this->output()->writeln(dt('Replacing "STARTERKIT" in all files…'));
    // Allow localize.drupal.org to pick up the string to translate.
    if (FALSE) {
      t('Replacing "STARTERKIT" in all files…');
    }

    // Iterate through the sub-theme directory finding files to filter.
    $directoryIterator = new \RecursiveDirectoryIterator($sub_theme_path);
    $starterKitFilter = new \RecursiveCallbackFilterIterator($directoryIterator, function ($current, $key, $iterator) {
      // Skip hidden files and directories.
      if ($current->getFilename()[0] === '.') {
        return FALSE;
      }
      // Skip node_modules and the asset-builds folder.
      elseif ($current->getFilename() === 'node_modules' || $current->getFilename() === 'asset-builds') {
        return FALSE;
      }
      // Recursively go through all folders.
      if ($current->isDir()) {
        return TRUE;
      }
      else {
        // Only return Twig templates or files with "STARTERKIT" in their name.
        return strpos($current->getFilename(), '.twig') !== FALSE || strpos($current->getFilename(), 'STARTERKIT') !== FALSE;
      }
    });

    $iterator = new \RecursiveIteratorIterator($starterKitFilter);
    $sub_theme_files = [];
    foreach ($iterator as $path => $info) {
      $sub_theme_files[$info->getFilename()] = $path;
    }

    // todo: extend this list.
    // Add more to the list of files to filter.
    $sub_theme_files['gulpfile.js'] = $sub_theme_path . '/gulpfile.js';
    $sub_theme_files['gulpfile.v3.js'] = $sub_theme_path . '/gulpfile.v3.js';
    $sub_theme_files['package.json'] = $sub_theme_path . '/package.json';
    // $sub_theme_files['theme-settings.php'] = $sub_theme_path . '/theme-settings.php';
    // $sub_theme_files['homepage.md'] = $sub_theme_path .
    // '/components/style-guide/homepage.md'; .

    foreach ($sub_theme_files as $filename) {
      // Replace all occurrences of 'STARTERKIT' with the machine name of our
      // sub theme.
      drush_op('materialize_helper_file_replace', $filename, [
        'STARTERKIT' => $machine_name,
        'THEMETITLE' => $name,
      ]);

      // Rename all files with STARTERKIT in their name.
      if (strpos($filename, 'STARTERKIT') !== FALSE) {
        drush_op('rename', $filename, str_replace('STARTERKIT', $machine_name, $filename));
      }
    }

    // Notify user of the newly created theme.
    $this->output()->writeln(dt('Starter kit for "!name" created in: !path',
      [
        '!name' => $name,
        '!path' => $sub_theme_path,
      ]
    ));
    // Allow localize.drupal.org to pick up the string to translate.
    if (FALSE) {
      t('Starter kit for "!name" created in: !path',
        [
          '!name' => $name,
          '!path' => $sub_theme_path,
        ]
      );
    }
  }

}
