<?php

namespace Drupal\gla_site\Form;

use Drupal\Core\Form\FormStateInterface;

/**
 * ProviderManagementDeleteForm.
 */
class ProviderManagementDeleteForm extends ProviderManagementBaseForm {

  protected $actionText = 'I want to delete this organisation';
  protected $actions = [
    'permanently delete their provider profile',
    'permanently delete their opportunities',
    'permanently delete applications to their opportunities',
    'permanently delete all users of the organisation',
  ];

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'gla_provider_management_delete_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $group = NULL) {
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
      $this->providerProcessor->deleteProviderOrganisation($group);
      $messenger->addMessage(t('Provider has been deleted.'), $messenger::TYPE_STATUS);
      $form_state->setRedirect($this->backRoute);
    }
    else {
      $form_state->setError($form, $this->t('This organisation could not be deleted. Please try again later.'));
    }
  }

}
