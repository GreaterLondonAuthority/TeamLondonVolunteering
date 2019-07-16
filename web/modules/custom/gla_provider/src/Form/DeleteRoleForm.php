<?php

namespace Drupal\gla_provider\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure example settings for this site.
 */
class DeleteRoleForm extends FormBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new MediaLibraryUploadForm.
   *
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
    return 'gla_provider_delete_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $node = NULL) {

    $form['delete'] = [
      '#type' => 'checkbox',
      '#required' => TRUE,
      '#title' => $this->t('I want to delete this role')
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

    $storage = $form_state->getStorage();
    $node = $storage['node'];
    if (!empty($node)) {
      $node_id = $node->id();
      $node->delete();
      // Delete all the applicants attached to this opportunity also.
      $applicant_submissions = $this->entityTypeManager->getStorage('application_submission')->loadByProperties([
        'node_id' => $node_id,
      ]);
      if (!empty($applicant_submissions)) {
        foreach ($applicant_submissions as $applicant_submission) {
          $applicant_submission->delete();
        }
      }
      $form_state->setRedirect('gla_provider.dashboard_archive_delete_success', ['action' => 'delete'], ['query' => ['id' => $node_id]]);
    }
    else {
      $form_state->setError($form, $this->t('This role could not be deleted. Please try again later.'));
    }

  }
}
