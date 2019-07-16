<?php

namespace Drupal\gla_opportunity\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityFormBuilder;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Link;
use Drupal\Core\Session\AccountProxy;
use Drupal\Core\Url;
use Drupal\gla_opportunity\Entity\ApplicationSubmission;
use Drupal\node\Entity\Node;
use Drupal\user\Entity\User;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Class GlaOpportunityApplicationController.
 */
class GlaOpportunityApplicationController extends ControllerBase {

  /**
   * @var EntityFormBuilder
   */
  protected $entityFormBuilder;

  /**
   * @var EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * @var User
   */
  protected $user;

  /**
   * Constructs a GlaOpportunityController object.
   *
   * @param EntityFormBuilder $entity_form_builder
   * @param EntityTypeManager $entity_type_manager
   */
  public function __construct(EntityFormBuilder $entity_form_builder, EntityTypeManager $entity_type_manager, AccountProxy $current_user) {
    $this->entityFormBuilder = $entity_form_builder;
    $this->entityTypeManager = $entity_type_manager;
    $this->user = User::load($current_user->id());
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity.form_builder'),
      $container->get('entity_type.manager'),
      $container->get('current_user')
    );
  }

  /**
   * The _title_callback for gla_opportunity.apply_overview.
   */
  public function applicationOverviewTitle(Node $node) {
    // If the user is not logged in, show the 'Register your interest' sign up
    // page.
    if ($this->currentUser()->isAnonymous()) {
      return $this->t('Register your interest');
    }

    // Otherwise it'll be the confirm your details page.
    return $this->t('Confirm your details');
  }

  /**
   * The _controller for gla_opportunity.apply_overview.
   */
  public function applicationOverview(Node $node) {
    // Only available to opportunity nodes.
    if ($node->bundle() != 'opportunity') {
      throw new AccessDeniedHttpException();
    }

    $back_url = Url::fromRoute('entity.node.canonical', ['node' => $node->id()]);

    // If the user is not logged in, show the 'Register your interest' sign up
    // page.
    if ($this->currentUser()->isAnonymous()) {

      // Generate links with this opportunity id in the query so we can get back
      // to it.
      $options = [
        'query' => [
          'opportunity' => $node->id(),
        ],
      ];

      $create_account = Link::createFromRoute(t('create an account'), 'multiple_registration.role_registration_page', ['rid' => 'volunteer'], $options);
      $sign_in = Link::createFromRoute(t('sign in'), 'user.login', [], $options);

      return [
        '#theme' => 'gla_opportunity__register_interest_sign_in',
        '#create_account' => $create_account,
        '#sign_in' => $sign_in,
        '#back_url' => $back_url,
        '#cache' => [
          'contexts' => [
            'user'
          ],
          'tags' => [
            'node:' . $node->id(),
          ],
        ],
      ];
    }

    $submission = $this->currentSubmission($node);

    $field_data = $this->applicationDataExtract($node, $submission);

    // Get the continue link.
    $continue = Link::createFromRoute(t('Get started'), 'gla_opportunity.apply', ['node' => $node->id()], [
      'attributes' => [
        'class' => [
          'button',
          'button--alt',
        ],
      ],
    ]);

    return [
      '#theme' => 'gla_opportunity__application_overview',
      '#sections' => $field_data,
      '#continue' => $continue,
      '#back_url' => $back_url,
      '#cache' => [
        'contexts' => [
          'user'
        ],
        'tags' => [
          'node:' . $node->id(),
        ],
      ],
    ];
  }

  /**
   * The _controller for gla_opportunity.apply.
   */
  public function applicationFormView(Node $node) {

    // Only available to opportunity nodes.
    if ($node->bundle() != 'opportunity') {
      throw new AccessDeniedHttpException();
    }

    // Load the submission and its entity form.
    $submission = $this->currentSubmission($node);
    $form = $this->entityFormBuilder->getForm($submission, 'default');

    return $form;
  }

  /**
   * The _controller for gla_opportunity.apply_check.
   */
  public function applicationCheck(Node $node) {
    // Only available to opportunity nodes.
    if ($node->bundle() != 'opportunity') {
      throw new AccessDeniedHttpException();
    }

    $submission = $this->currentSubmission($node);

    $field_data = $this->applicationDataExtract($node, $submission);

    // Get the submit form.
    $submit_form = \Drupal::formBuilder()->getForm('\Drupal\gla_opportunity\Form\ApplicationSubmissionSubmitForm', $submission);

    return [
      '#theme' => 'gla_opportunity__application_check',
      '#sections' => $field_data,
      '#continue' => $submit_form,
      '#cache' => [
        'contexts' => [
          'user'
        ],
        'tags' => [
          'node:' . $node->id(),
        ],
      ],
    ];
  }

  /**
   * The _controller for gla_opportunity.apply_success.
   */
  public function applicationSuccess(Node $node) {
    // Only available to opportunity nodes.
    if ($node->bundle() != 'opportunity') {
      throw new AccessDeniedHttpException();
    }

    // Get the user's submitted submission.
    $submission = $this->loadUserActiveSubmission($node, TRUE);
    if (!$submission) {
      // Can't access this page if they haven't submitted anything.
      throw new AccessDeniedHttpException();
    }

    // Get the params for the template.
    $links = [];
    $email = $submission->get('field_email')->getString();
    $links[] = Link::createFromRoute(t('Return to opportunity: @opp_name', [
      '@opp_name' => $node->getTitle(),
    ]), 'entity.node.canonical', ['node' => $node->id()]);
    $links[] = Link::createFromRoute(t('Search for more volunteer opportunities'), 'view.profile_search.page_1');
    $links[] = Link::createFromRoute(t('Return to Team London homepage'), '<front>');

    return [
      '#theme' => 'gla_opportunity__application_success',
      '#email' => $email,
      '#links' => $links,
      '#cache' => [
        'contexts' => [
          'user'
        ],
        'tags' => [
          'node:' . $node->id(),
        ],
      ],
    ];
  }

  /**
   * Load an active submission entity or a new one if none exists.
   */
  public function currentSubmission(Node $node) {

    // Only available to opportunity nodes.
    if ($node->bundle() != 'opportunity') {
      throw new AccessDeniedHttpException();
    }

    // Check if there is one in progress.
    $submission = $this->loadUserActiveSubmission($node);
    if (!$submission) {
      // None in progress, load a skeleton one.
      $submission = $this->loadSkeletonSubmission($node);
    }

    return $submission;
  }

  /**
   * Load the form for a skeleton submission entity for the current user.
   */
  public function loadSkeletonSubmission(Node $node) {
    // Only available to opportunity nodes.
    if ($node->bundle() != 'opportunity') {
      return FALSE;
    }

    // Load the submission entity add form and set some hidden values.
    $submission_name = $this->t('TeamLondon application: @node_title [volunteer id: @user_id]', [
      '@node_title' => $node->getTitle(),
      '@user_id' => $this->user->id(),
    ]);

    $submission = $this->entityTypeManager->getStorage('application_submission')->create([
      'name' => $submission_name,
      'node_id' => $node->id(),
      'field_email' => $this->user->getEmail(),
      'field_first_name' => $this->user->get('field_first_name')->getString(),
      'field_last_name' => $this->user->get('field_last_name')->getString(),
    ]);

    return $submission;
  }

  /**
   * Load the currently active submission entity for the current user.
   */
  public function loadUserActiveSubmission(Node $node, $submitted = FALSE) {
    // Only available to opportunity nodes.
    if ($node->bundle() != 'opportunity') {
      return FALSE;
    }

    $submissions = $this->entityTypeManager->getStorage('application_submission')->loadByProperties([
      'user_id' => $this->user->id(),
      'node_id' => $node->id(),
      'submitted' => $submitted,
    ]);

    if (!empty($submissions)) {
      return end($submissions);
    }

    return FALSE;
  }

  /**
   * Extract the data we need.
   */
  public function applicationDataExtract(Node $node, ApplicationSubmission $submission) {

    // Load the submission entity edit form.
    $submission_form = $this->entityFormBuilder->getForm($submission, 'default');

    // Sort through form to get step for each question.
    $step_map = [];
    foreach ($submission_form['#steps'] as $step_num => $step_data) {
      foreach ($step_data->children as $child) {
        if (substr($child, 0, 5) !== 'step_') {
          $step_map[$child] = $step_num;
        }
      }
    }

    // Extract the values to display and generate the edit link.
    $sections = [
      'email' => [
        'label' => t('Email address'),
        'fields' => [
          'field_email',
        ],
      ],
      'full_name' => [
        'label' => t('Full name'),
        'fields' => [
          'field_first_name',
          'field_last_name',
        ],
      ],
      'qu1' => [
        'fields' => [
          'field_tell_us_why',
        ],
      ],
      'qu2' => [
        'fields' => [
          'field_special_requirements',
        ],
      ],
    ];

    $data = [];
    $weight = 0;
    foreach ($sections as $key => $info) {
      $fields = $info['fields'];
      $section_values = [];

      // Collect the field values in this section.
      $step_num = FALSE;
      foreach ($fields as $field) {
        if ($submission->hasField($field) && isset($step_map[$field])) {
          $step_num = $step_map[$field];
          $section_values[] = trim($submission->$field->getString());
        }
      }

      if (!$step_num) {
        continue;
      }

      // Grab title.
      if (isset($info['label'])) {
        $label = $info['label'];
      }
      else {
        // We want to show the step title in the output.
        $step_field_name = $submission_form['#steps'][$step_num]->children[0];
        $label = $submission_form[$step_field_name]['widget']['#title'];
      }

      // Get the link to change the answer.
      $link_classes = 'link link--edit';
      $link_title = t('Change');
      $edit_link = Link::createFromRoute($link_title, 'gla_opportunity.apply', ['node' => $node->id()], [
        'query' => [
          'step' => $step_num,
        ],
        'attributes' => [
          'class' => $link_classes,
        ],
      ]);

      $weight++;
      $data[$key] = [
        'label' => $label,
        'value' => implode(' ', $section_values),
        'link' => $edit_link,
      ];
    }

    return $data;
  }
}
