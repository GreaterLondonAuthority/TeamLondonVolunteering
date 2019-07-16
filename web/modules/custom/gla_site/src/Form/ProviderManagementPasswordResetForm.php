<?php

namespace Drupal\gla_site\Form;

use Drupal\Core\Form\FormStateInterface;

/**
 * ProviderManagementPasswordResetForm.
 */
class ProviderManagementPasswordResetForm extends ProviderManagementBaseForm {

  protected $actionText = 'I want to send a password reset link to this user';
  protected $actions = [];

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'gla_provider_management_password_reset_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $user = NULL) {
    return parent::buildForm($form, $form_state, $user);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    $messenger = \Drupal::messenger();
    $storage = $form_state->getStorage();
    /** @var \Drupal\user\Entity\User $user */
    $user = $storage['entity'];
    if (!empty($user)) {
      _user_mail_notify('password_reset', $user);
      $messenger->addMessage(t('Password reset link has been sent.'), $messenger::TYPE_STATUS);
      $form_state->setRedirect($this->backRoute);
    }

  }

}
