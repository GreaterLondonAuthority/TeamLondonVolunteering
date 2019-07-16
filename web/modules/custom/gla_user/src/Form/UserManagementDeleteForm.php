<?php

namespace Drupal\gla_user\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\gla_provider\ProviderProcessor;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * UserManagementDeleteForm.
 */
class UserManagementDeleteForm extends FormBase {

  /**
   * @var ProviderProcessor
   */
  protected $providerProcessor;

  /**
   * @var string
   */
  protected $lastProviderMessage = 'Your account is the last in your organisation. Please request complete deletion from the site admin.';

  /**
   * @param ProviderProcessor $provider_processor
   */
  public function __construct(ProviderProcessor $provider_processor) {
    $this->providerProcessor = $provider_processor;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('gla_provider.processor')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'gla_user_management_delete_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $user = NULL, $role = NULL) {

    $back_route_name = "gla_$role.dashboard";
    if (!$this->providerProcessor->userCanDelete($user)) {
      $messenger = \Drupal::messenger();
      $messenger->addMessage(t($this->lastProviderMessage), $messenger::TYPE_WARNING);
      return $this->redirect($back_route_name);
    }

    $form['description'] = [
      '#markup' => $this->getDescriptionText($role),
    ];

    $form['confirm'] = [
      '#type' => 'checkbox',
      '#required' => TRUE,
      '#title' => t('Please confirm you want to delete your account.'),
    ];

    $back_route_name = "gla_$role.dashboard";
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Confirm'),
      '#suffix' => Link::createFromRoute($this->t('Cancel and go back'), $back_route_name)->toString(),
    ];

    $form_state->setStorage([
      'user' => $user,
      'role' => $role,
    ]);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    $messenger = \Drupal::messenger();
    $storage = $form_state->getStorage();
    $user = $storage['user'];
    $role = $storage['role'];
    if (!empty($user) && !empty($role)) {
      // Set up the batch process.
      $batch = $this->providerProcessor->deleteUserBatch($user);
      if ($batch) {
        batch_set($batch);
      }
      else {
        $messenger->addMessage(t($this->lastProviderMessage), $messenger::TYPE_WARNING);
      }
    }
    else {
      $messenger->addMessage(t('Your account could not be deleted. Please try again later.'), $messenger::TYPE_WARNING);
    }
  }

  /**
   * @return string
   */
  public function getDescriptionText($role) {
    switch ($role) {
      case 'volunteer':
        $text = t('Deleting your account will permanently delete all your applications to roles and your account itself.');
        break;
      case 'provider':
        $text = t('Deleting your account will transfer any content you\'ve created to another member of your organisation. Then your account will be permanently deleted. If you want to delete your entire organisation instead, please contact a site admin.');
        break;
    }

    return "<h3>$text</h3>";
  }

}
