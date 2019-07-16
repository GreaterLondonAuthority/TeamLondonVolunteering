<?php

namespace Drupal\gla_provider\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Delete provider member form.
 */
class DeleteMemberForm extends FormBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'gla_provider_delete_member_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $group = NULL, $user = NULL) {

    // User cannot delete their own account like this.
    $current_user = \Drupal::currentUser();
    if ($group && $user && $current_user->id() == $user->id()) {
      drupal_set_message(t('You cannot delete your own account in this way. Please use the \'Delete my account\' option through your dashboard.'), 'warning');
      $url = Url::fromRoute('gla_provider.user_view', ['group' => $group->id(), 'user' => $user->id()])->toString();
      return new RedirectResponse($url);
    }

    $form['delete'] = [
      '#type' => 'checkbox',
      '#required' => TRUE,
      '#title' => $this->t('I want to delete this member')
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Continue'),
      '#suffix' => Link::createFromRoute($this->t('Cancel and go back'), 'gla_provider.user_view', [
        'group' => $group->id(),
        'user' => $user->id(),
      ])->toString()
    ];

    $form_state->setStorage([
      'member_to_delete' => $user,
      'group' => $group,
    ]);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    $storage = $form_state->getStorage();
    $member_to_delete = $storage['member_to_delete'];
    /** @var \Drupal\group\Entity\Group $group */
    $group = $storage['group'];
    if (!empty($member_to_delete) && !empty($group)) {
      $all_members = $group->getMembers();
      if (count($all_members) == 1) {
        // Cannot delete.
        drupal_set_message(t('There is only one remaining member of this organisation. Please contact a site admin to delete the entire organisation.'), 'warning');
        return;
      }

      // Use user_cancel with our custom 'user_delete_reassign_self' method.
      $form_state->setValue('user_cancel_method', 'user_delete_reassign_self');
      user_cancel([], $member_to_delete->id(), 'user_delete_reassign_self');
      $form_state->setRedirect('view.provider_group_members.page_1', ['group' => $group->id()]);
    }
    else {
      drupal_set_message(t('This member could not be deleted. Please try again later.'), 'warning');
    }
  }

}
