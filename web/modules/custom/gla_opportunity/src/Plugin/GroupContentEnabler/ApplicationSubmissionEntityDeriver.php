<?php

namespace Drupal\gla_opportunity\Plugin\GroupContentEnabler;

use Drupal\Component\Plugin\Derivative\DeriverBase;

class ApplicationSubmissionEntityDeriver extends DeriverBase {

  /**
   * {@inheritdoc}.
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    $this->derivatives['application_submission'] = [
      'entity_bundle' => FALSE,
      'label' => t('Group entity (Application Submission)'),
      'description' => t('Adds Application Submissions to groups.'),
    ] + $base_plugin_definition;
    return $this->derivatives;
  }
}
