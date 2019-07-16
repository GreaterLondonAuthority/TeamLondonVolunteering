<?php

namespace Drupal\gla_provider\Form;

use Drupal\content_moderation\Form\EntityModerationForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Entity\RevisionLogInterface;

/**
 * Configure example settings for this site.
 */
class UnpublishRoleForm extends EntityModerationForm {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'gla_provider_unpublish_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $node = NULL) {

    $form['unpublish'] = [
      '#type' => 'checkbox',
      '#required' => TRUE,
      '#title' => $this->t('Are you sure you want to unpublish this role?')
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Continue'),
      '#suffix' => Link::createFromRoute($this->t('Cancel and go back'), 'gla_provider.dashboard_opportunity_edit', ['node' => $node->id()])->toString()
    ];

    $form_state->setStorage([
      'node' => $node,
    ]);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $entity = $form_state->getStorage()['node'];
    /** @var \Drupal\Core\Entity\ContentEntityStorageInterface $storage */
    $storage = \Drupal::entityTypeManager()->getStorage($entity->getEntityTypeId());
    $entity = $storage->createRevision($entity, $entity->isDefaultRevision());
    $entity->set('moderation_state', 'unpublished');
    if ($entity instanceof RevisionLogInterface) {
      $entity->setRevisionCreationTime($this->time->getRequestTime());
      $entity->setRevisionLogMessage($form_state->getValue('revision_log'));
      $entity->setRevisionUserId($this->currentUser()->id());
    }
    $entity->save();

    $this->messenger()->addStatus($this->t('Role has been archived.'));
    $form_state->setRedirect('gla_provider.dashboard_archive_delete_success', ['action' => 'unpublished'], ['query' => ['id' => $entity->id()]]);

  }
}
