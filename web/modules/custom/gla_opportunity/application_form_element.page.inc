<?php

/**
 * @file
 * Contains application_form_element.page.inc.
 *
 * Page callback for Application form element entities.
 */

use Drupal\Core\Render\Element;

/**
 * Prepares variables for Application form element templates.
 *
 * Default template: application_form_element.html.twig.
 *
 * @param array $variables
 *   An associative array containing:
 *   - elements: An associative array containing the user information and any
 *   - attributes: HTML attributes for the containing element.
 */
function template_preprocess_application_form_element(array &$variables) {
  // Fetch ApplicationFormElement Entity Object.
  $application_form_element = $variables['elements']['#application_form_element'];

  // Helpful $content variable for templates.
  foreach (Element::children($variables['elements']) as $key) {
    $variables['content'][$key] = $variables['elements'][$key];
  }
}
