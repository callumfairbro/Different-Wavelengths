/**
 * @file
 * A JavaScript file for the theme.
 *
 * In order for this JavaScript to be loaded on pages, see the instructions in
 * the README.txt next to this file.
 */

// JavaScript should be made compatible with libraries other than jQuery by
// wrapping it with an "anonymous closure". See:
// - https://drupal.org/node/1446420
// - http://www.adequatelygood.com/2010/3/JavaScript-Module-Pattern-In-Depth
(function ($, Drupal) {

  'use strict';

  // To understand behaviors, see https://drupal.org/node/756722#behaviors
  Drupal.behaviors.materialize = {
    attach: function (context, settings) {

      if (typeof(Waves) !== 'undefined') {
        // todo: add theme variable to allow auto wave on buttons.
        // Waves.attach('.btn:not(.btn-icon):not(.btn-float)');
        Waves.attach('.waves-effect', ['waves-circle', 'waves-block']);
        Waves.attach('.btn', ['waves-button']);
        Waves.init();
      }

    }
  };

  // Sidenav toggle.
  $('.sidenav-trigger').click(function(e) {
    $('body').addClass('sidenav-open');
    e.stopPropagation();
    e.preventDefault();
  });

  $('.side-nav, .sidenav-close .button-close').click(function(e) {
    $('body').removeClass('sidenav-open');
    e.stopPropagation();
    e.preventDefault();
  });

  // Add jQuery touch events.
  $('.side-nav').swipeleft(function() {
    $('body').removeClass('sidenav-open');
  });

  $('.side-nav').swiperight(function() {
    $('body').addClass('sidenav-open');
  });

})(jQuery, Drupal);
