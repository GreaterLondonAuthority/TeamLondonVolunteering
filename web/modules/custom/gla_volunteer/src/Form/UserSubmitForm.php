<?php

namespace Drupal\gla_volunteer\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\Entity\Node;
use Drupal\user\Entity\User;

/**
 * Class UserSubmitForm
 *
 * Form for submitting and validating user profiles responses.
 *
 * @package Drupal\gla_volunteer\Form
 */
class UserSubmitForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'gla_user_user_submit_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, User $user = NULL) {

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => t('Submit and continue'),
      '#attributes' => [
        'class' => [
          'button--alt',
        ],
      ],
    ];

    $form['user'] = [
      '#type' => 'value',
      '#value' => $user,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // Validate the responses before allowing the user to continue.
    /** @var User $entity */
    $entity = $form_state->getValue('user');
    if (!$entity) {
      // If we get here, something has gone wrong.
      $form_state->setError($form, t('There was a problem with your submission, please refresh the page and try again.'));
      return;
    }

    /** @var \Drupal\Core\Entity\EntityConstraintViolationList $violations[] */
    $violations = $entity->validate();

    // Remove violations of inaccessible fields.
    $current_user = \Drupal::currentUser();
    $violations->filterByFieldAccess($current_user);

    // Get the user form to lookup the title to use in the validation error message.
    $user_form_obj = \Drupal::entityTypeManager()
      ->getFormObject($entity->getEntityTypeId(), 'equal_opportunities')
      ->setEntity($entity);
    $user_form =  \Drupal::formBuilder()->getForm($user_form_obj);

    $errors = [];
    foreach ($violations->getFieldNames() as $field_name) {
      // Set a form error for every user save validation violation. As we're not
      // actually acting on the user form (i.e. don't have these fields in the
      // form) we group all the errors together in one setError.
      // Find the step this question is on to get the message to display. Fallback
      // to the field name in case we can't find the step title for some reason.
      foreach ($user_form['#steps'] as $step_number => $step_data) {
        if (in_array($field_name, $step_data->children)) {
          $label_field = $step_data->children[0];
          $label = $user_form[$label_field]['widget']['#title'];
          $errors[$step_data->children[0]] = $label;
          break;
        }
      }
    }

    // Special check for the T&Cs.
    $t_and_cs = $entity->get('field_tandc')->getValue();
    if (!isset($t_and_cs[0], $t_and_cs[0]['value']) || $t_and_cs[0]['value'] == 0) {
      // Must accept the T&Cs.
      $errors['field_tandc'] = $user_form['field_tandc']['widget']['#title'];
    }

    if (!empty($errors)) {
      $items = [
        '#theme' => 'item_list',
        '#list_type' => 'ul',
        '#items' => $errors,
      ];

      $form_state->setError($form, t('Answers for the following questions are required before you can proceed: <br> @items', [
        '@items' => render($items),
      ]));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // If this user has just signed up after trying to register an interest in
    // an opportunity, redirect them back to it here.
    /** @var User $entity */
    $entity = $form_state->getValue('user');
    $signed_up_from = $entity->get('field_signed_up_from')->entity;
    if ($signed_up_from && $signed_up_from instanceof Node) {
      $form_state->setRedirect('gla_opportunity.apply_overview', ['node' => $signed_up_from->id()]);
      // Unset the value on the user so they're not always taken here.
      $entity->set('field_signed_up_from', NULL);
      $entity->save();
    }
    else {
      // Get the normal continue link.
      $nid = $this->config('gla_site.registration_flow_settings')->get('node:equal_opportunities_submitted');
      $form_state->setRedirect('entity.node.canonical', ['node' => $nid]);
    }
  }
}
