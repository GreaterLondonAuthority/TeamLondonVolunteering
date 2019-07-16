<?php

namespace Drupal\gla_opportunity\Plugin\GroupContentEnabler;

use Drupal\group\Plugin\GroupContentEnablerBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a content enabler for user entities.
 *
 * @GroupContentEnabler(
 *   id = "group_entity_application_submission",
 *   label = @Translation("Application Submission"),
 *   description = @Translation("Adds Application Submission to groups."),
 *   entity_type_id = "application_submission",
 *   entity_access = TRUE,
 *   reference_label = @Translation("Label"),
 *   reference_description = @Translation("The name of the Submission (entity) to add to the group"),
 *   deriver = "Drupal\gla_opportunity\Plugin\GroupContentEnabler\ApplicationSubmissionEntityDeriver"
 * )
 */
class ApplicationSubmissionEntity extends GroupContentEnablerBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    $config = parent::defaultConfiguration();
    $config['entity_cardinality'] = 1;
    return $config;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);
    // Disable the entity cardinality field as the functionality of this module
    // relies on a cardinality of 1. We don't just hide it, though, to keep a UI
    // that's consistent with other content enabler plugins.
    $info = $this->t("This field has been disabled by the plugin to guarantee the functionality that's expected of it.");
    $form['entity_cardinality']['#disabled'] = TRUE;
    $form['entity_cardinality']['#description'] .= '<br /><em>' . $info . '</em>';

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    return ['module' => ['gla_opportunity']];
  }

}
