<?php

namespace Drupal\gla_site\Form;

use Drupal\Core\Form\FormStateInterface;

/**
 * ProviderManagementSuspendForm.
 */
class ProviderManagementSuspendForm extends ProviderManagementBaseForm {

  protected $actionText = 'I want to suspend this organisation';
  protected $actions = [
    'unpublish their provider profile',
    'unpublish their opportunities',
    'prevents provider users responding to applications',
  ];

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'gla_provider_management_suspend_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $group = NULL) {
    // Add note if already suspended.
    if ($group->get('field_suspended')->value) {
      $messenger = \Drupal::messenger();
      $messenger->addMessage(t('This provider is already suspended.'), $messenger::TYPE_WARNING);
    }
    return parent::buildForm($form, $form_state, $group);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    $messenger = \Drupal::messenger();
    $storage = $form_state->getStorage();
    /** @var \Drupal\group\Entity\Group $group */
    $group = $storage['entity'];
    if (!empty($group)) {
      $this->providerProcessor->suspendProviderOrganisation($group);
      $messenger->addMessage(t('Provider has been suspended.'), $messenger::TYPE_STATUS);
      $form_state->setRedirect($this->backRoute);
    }
    else {
      $form_state->setError($form, $this->t('This organisation could not be suspended. Please try again later.'));
    }
  }

}
