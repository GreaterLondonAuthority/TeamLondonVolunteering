<?php

namespace Drupal\gla_opportunity\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\gla_opportunity\Entity\ApplicationFormElement;

/**
 * Form controller for Application submission edit forms.
 *
 * @ingroup gla_opportunity
 */
class ApplicationSubmissionForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);

    /** @var \Drupal\gla_opportunity\Entity\ApplicationSubmission $submission */
    $submission = $this->entity;

    $application_routes = [
      'gla_opportunity.apply',
      'gla_opportunity.apply_overview',
    ];

    $route_match = \Drupal::routeMatch();
    if (in_array($route_match->getRouteName(), $application_routes)) {
      // Hide some fields from the application view.
      $form['user_id']['#access'] = FALSE;
      $form['node_id']['#access'] = FALSE;
      $form['submitted']['#access'] = FALSE;
    }

    // Check if we should show the extra questions.
    $questions = $submission->showAdditionalQuestions();
    foreach ($questions as $field_name => $show) {
      if (isset($form[$field_name])) {
        $form[$field_name]['#access'] = $show;
      }
    }

    // Hide some labels.
    $form['field_special_requirements']['widget'][0]['value']['#title_display'] = 'invisible';

    return $form;
  }

}
