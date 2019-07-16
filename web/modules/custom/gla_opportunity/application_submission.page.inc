<?php

/**
 * @file
 * Contains application_submission.page.inc.
 *
 * Page callback for Application submission entities.
 */

use Drupal\Core\Render\Element;

/**
 * Prepares variables for Application submission templates.
 *
 * Default template: application_submission.html.twig.
 *
 * @param array $variables
 *   An associative array containing:
 *   - elements: An associative array containing the user information and any
 *   - attributes: HTML attributes for the containing element.
 */
function template_preprocess_application_submission(array &$variables) {
  // Fetch ApplicationSubmission Entity Object.
  $application_submission = $variables['elements']['#application_submission'];

  // Helpful $content variable for templates.
  foreach (Element::children($variables['elements']) as $key) {
    $variables['content'][$key] = $variables['elements'][$key];
  }
}
