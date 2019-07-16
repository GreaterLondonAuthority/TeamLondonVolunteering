<?php

namespace Drupal\gla_site\Form;

use Drupal\Core\Form\FormStateInterface;

/**
 * ProviderManagementReactivateForm.
 */
class ProviderManagementReactivateForm extends ProviderManagementBaseForm {

  protected $actionText = 'I want to re-activate this organisation';
  protected $actions = [
    'restore the public version of their provider profile',
    'restore public versions of their opportunities',
    'allow the provider users to respond to applications',
  ];

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'gla_provider_management_reactivate_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $group = NULL) {
    $suspended = $group->get('field_suspended')->value;
    if ($suspended != 1) {
      // This organisation is already active.
      $title = $this->providerProcessor->getProviderProfileFromEntity($group)->getTitle();
      $form['name'] = [
        '#markup' => '<h1>' . t('Selected organisation') . ': ' . $title . '</h1>',
      ];

      $form['description'] = [
        '#markup' => t('This organisation is not suspended, therefore cannot be re-activated.'),
      ];

      return $form;
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
      $this->providerProcessor->reactivateProviderOrganisation($group);
      $messenger->addMessage(t('Provider has been re-activated.'), $messenger::TYPE_STATUS);
      $form_state->setRedirect($this->backRoute);
    }
    else {
      $form_state->setError($form, $this->t('This organisation could not be re-activated. Please try again later.'));
    }
  }

}
