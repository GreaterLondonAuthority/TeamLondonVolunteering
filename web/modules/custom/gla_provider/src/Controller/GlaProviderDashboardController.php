<?php

namespace Drupal\gla_provider\Controller;

use Drupal\content_moderation\ModerationInformation;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityFormBuilder;
use Drupal\Core\Link;
use Drupal\gla_provider\ProviderProcessor;
use Drupal\node\Entity\Node;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\gla_opportunity\Controller\GlaOpportunityController;
use Drupal\Core\Form\FormBuilderInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\Core\Url;

/**
 * Class GlaProviderController.
 */
class GlaProviderDashboardController extends GlaOpportunityController {

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $request;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The database connection object.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * The form builder.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected $formBuilder;

  /**
   * @var \Drupal\content_moderation\ModerationInformation
   */
  protected $moderationInformation;

  /**
   * Constructs a GlaOpportunityController object.
   *
   * @param EntityFormBuilder $entity_form_builder
   * @param ProviderProcessor $provider_processor
   * @param \Drupal\Core\Form\FormBuilderInterface $form_builder
   */
  public function __construct(ProviderProcessor $provider_processor, EntityFormBuilder $entity_form_builder, RequestStack $request, EntityTypeManagerInterface $entity_type_manager, Connection $connection, FormBuilderInterface $form_builder, ModerationInformation $moderation_information) {
    parent::__construct($provider_processor, $entity_form_builder);
    $this->request = $request->getCurrentRequest();
    $this->entityTypeManager = $entity_type_manager;
    $this->connection = $connection;
    $this->formBuilder = $form_builder;
    $this->moderationInformation = $moderation_information;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('gla_provider.processor'),
      $container->get('entity.form_builder'),
      $container->get('request_stack'),
      $container->get('entity_type.manager'),
      $container->get('database'),
      $container->get('form_builder'),
      $container->get('content_moderation.moderation_information')
    );
  }

  /**
   * Controller for opportunities overview.
   *
   * @return array
   */
  public function providerOpportunityOverview() {

    // Get the get user's group.
    $user = $this->currentUser();
    $group = $this->providerProcessor->getGroup($user);

    $roles = [];
    // List of all role views to display.
    $role_displays = [
      'archived' => $this->t('Archived Roles'),
      'draft' => $this->t('Roles you\'ve started'),
      'ready_for_review' => $this->t('Roles you\'ve submitted'),
      'published' => $this->t('Published Roles'),
      'approved' => $this->t('Approved roles yet to be advertised'),
      'unpublished' => $this->t('Unpublished Roles'),
      'expired' => $this->t('Expired Roles'),
      'feedback' => $this->t('Roles with Feedback'),
    ];
    $provider_profile = $this->providerProcessor->getUserProviderProfile($user, TRUE);
    if ($provider_profile) {
      $provider_name = $provider_profile->getTitle();
    }
    // Go through role and build the roles to be rendered.
    foreach ($role_displays as $moderation_state => $role_display_title) {
      $moderation_state_roles = $this->buildViewForModerationState($group, $moderation_state);
      $roles[$moderation_state]['title'] = $role_display_title;
      if (!empty($moderation_state_roles)) {
        foreach ($moderation_state_roles as $id => $role) {
          // Load the node revision that comes back and extract the needed data for each.
          $revision_id = $role->content_entity_revision_id;
          $opportunity = $this->entityTypeManager->getStorage('node')->loadRevision($revision_id);

          $when = t('TBC');
          $when_needed = $opportunity->get('field_dates_needed')->value;
          switch ($when_needed) {
            case 'one_off':
              if ($opportunity->get('field_one_off_date')->date) {
                $when = $opportunity->get('field_one_off_date')->date->format('d/m/Y');
              }
              break;
            case 'ongoing':
              if ($opportunity->get('field_ongoing_start_date')->date) {
                $when = t('Ongoing') . ': ' . $opportunity->get('field_ongoing_start_date')->date->format('d/m/Y');
              }
              else {
                $when = t('Ongoing');
              }
              break;
          }

          $title = trim($opportunity->getTitle());
          if (empty($title)) {
            $title = '<Unnamed>';
          }

          // If the opportunity has been submitted for review link to the node
          // view as the provider cannot make changes at this time.
          if ($moderation_state == 'ready_for_review') {
            $node_view_route_name = 'entity.node.canonical';
            if ($this->moderationInformation->hasPendingRevision($opportunity)) {
              $node_view_route_name = 'entity.node.latest_version';
            }

            $link_to_edit_node = Link::createFromRoute($title, $node_view_route_name, ['node' => $opportunity->id()], ['query' => ['dashboard' => 1]]);
          }
          elseif ($opportunity->isPublished()) {
            $link_to_edit_node = Link::createFromRoute($title, 'gla_provider.dashboard_opportunity_change', ['node' => $opportunity->id()]);
          }
          else {
            $link_to_edit_node = Link::createFromRoute($title, 'gla_provider.dashboard_opportunity_edit', ['node' => $opportunity->id()]);
          }

          // Set the array to be rendered containing title link and when.
          $roles[$moderation_state]['roles'][] = [
            'id' => $opportunity->id(),
            'title' => $opportunity->getTitle(),
            'link' => $link_to_edit_node,
            'when' => isset($when) ? $when : '',
          ];
        }
      }
    }

    $tags = [];
    if ($group) {
      $tags = ['provider_opportunities_group:' . $group->id()];
    }

    return [
      '#theme' => 'gla_provider__overview',
      '#roles' => $roles,
      '#add_content' => Link::createFromRoute($this->t('Create new role'), 'gla_opportunity.new', [], ['attributes' => ['class' => 'button button--alt']]),
      '#organisation' => $provider_name,
      '#cache' => [
        'contexts' => [
          'user',
        ],
        'tags' => $tags,
      ],
    ];
  }

  /**
   * Helper function that queries the db to return the nodes for the respective moderation state.
   *
   * @param $group
   * @param $moderation_state
   * @param array $extra_condition
   * @return mixed
   */
  private function buildViewForModerationState($group, $moderation_state, array $extra_condition = []) {
    // First look at the default revisions.
    $query = $this->connection->select('node_field_data', 'nf');
    // Joins on moderation_state and node_field_data.
    $query->join('content_moderation_state_field_data', 'ms', 'nf.nid=ms.content_entity_id AND nf.vid=ms.content_entity_revision_id');
    $query->fields('ms', ['content_entity_revision_id', 'content_entity_id']);
    // Unique case as expired needs to deal with those that are in the past.
    switch ($moderation_state) {
      case 'expired':
        $query->join('node__field_end_of_ad_specific', 'ads', 'nf.nid=ads.entity_id');
        $query->condition('ads.field_end_of_ad_specific_value', date('Y-m-d'), '<');
        break;
      case 'feedback':
        $query->join('node_revision', 'nr', 'nf.nid=nr.nid AND nr.vid=nf.vid');
        $query->condition('ms.moderation_state', 'draft');
        $query->isNotNull('nr.revision_log');
        break;
      case 'draft':
        $query->join('node_revision', 'nr', 'nf.nid=nr.nid AND nr.vid=nf.vid');
        $query->condition('ms.moderation_state', $moderation_state);
        $query->isNull('nr.revision_log');
        break;
      default:
        $query->condition('ms.moderation_state', $moderation_state);
        break;
    }

    // We need to filter on all content belonging to this user's group if they
    // belong to one. Just their user if not.
    if ($group) {
      $query->join('group_content_field_data', 'gd', 'nf.nid=gd.entity_id');
      $query->condition('gd.gid', $group->id());
      $query->condition('gd.type', 'provider-group_node-opportunity');
    }
    else {
      $query->condition('nf.uid', $this->currentUser()->id());
    }

    $query->condition('nf.type', 'opportunity');
    $result = $query->execute()->fetchAllAssoc('content_entity_id');

    // Then look for any other latest versions.
    $query = $this->connection->select('node_field_revision', 'nf');
    $query->join('node_field_data', 'nfd', 'nf.nid=nfd.nid');
    $query->join('content_moderation_state_field_revision', 'ms', 'nf.nid=ms.content_entity_id AND nf.vid=ms.content_entity_revision_id');
    $query->fields('ms', ['content_entity_revision_id', 'content_entity_id']);
    // Unique case as expired needs to deal with those that are in the past.
    switch ($moderation_state) {
      case 'expired':
        $query->join('node_revision__field_end_of_ad_specific', 'ads', 'nf.vid=ads.revision_id');
        $query->condition('ads.field_end_of_ad_specific_value', date('Y-m-d'), '<');
        break;
      case 'feedback':
        $query->join('node_revision', 'nr', 'nr.vid=nf.vid');
        $query->condition('ms.moderation_state', 'draft');
        $query->isNotNull('nr.revision_log');
        break;
      case 'draft':
        $query->join('node_revision', 'nr', 'nr.vid=nf.vid');
        $query->condition('ms.moderation_state', $moderation_state);
        $query->isNull('nr.revision_log');
        break;
      default:
        $query->condition('ms.moderation_state', $moderation_state);
        break;
    }

    // We need to filter on all content belonging to this user's group if they
    // belong to one. Just their user if not.
    if ($group) {
      $query->join('group_content_field_data', 'gd', 'nf.nid=gd.entity_id');
      $query->condition('gd.gid', $group->id());
      $query->condition('gd.type', 'provider-group_node-opportunity');
    }
    else {
      $query->condition('nf.uid', $this->currentUser()->id());
    }

    $query->condition('nfd.type', 'opportunity');
    $query->orderBy('ms.content_entity_revision_id');
    $moderation_state_revisions = $query->execute()->fetchAllAssoc('content_entity_id');

    // Only include if it's the latest revision of each node.
    foreach ($moderation_state_revisions as $moderation_state_revision) {
      $moderation_state_nid = $moderation_state_revision->content_entity_id;
      $moderation_state_vid = $moderation_state_revision->content_entity_revision_id;
      $latest_revision_id = $this->moderationInformation->getLatestRevisionId('node', $moderation_state_nid);
      if ($moderation_state_vid == $latest_revision_id) {
        if (!isset($result[$moderation_state_nid])) {
          $result[$moderation_state_nid] = $moderation_state_revision;
          $result[$moderation_state_nid]->isPendingRevision = TRUE;
        }
      }
    }

    return $result;
  }

  /**
   * Controller for editing nodes on provider profile overview.
   *
   * @param $node
   */
  public function editOpportunity($node) {

    $data = $this->opportunityDataExtract($node, '');

    // Get the moderation state change form to trigger the 'ready for review' transition.
    // It is altered in gla_provider.module.
    $moderation_form = $this->formBuilder->getForm('\Drupal\content_moderation\Form\EntityModerationForm', $node);

    // Splitting the data up into appropriate sections.
    $grouped_steps = [
      'role_at_a_glance' => array_slice($data, 0, 6),
      'role_details' => array_slice($data, 6, 6),
      'additional_information' => array_slice($data, 12, 6),
      'questions' => array_slice($data, 18, 2),
    ];

    $has_changes = FALSE;
    // Set particular actions depending on if the node is published or not.
    if ($node->isPublished()) {
      $actions = [
        'published' => Link::createFromRoute($this->t('See published role'), 'entity.node.canonical', ['node' => $node->id()], ['attributes' => ['target' => '_blank']]),
        'unpublish' => Link::createFromRoute($this->t('Unpublish role'), 'gla_provider.dashboard_opportunity_unpublish', ['node' => $node->id()]),
      ];
    }
    else {
      // If the user is coming back after changing a published record, they will see a different set of actions.
      $has_changes = $this->providerProcessor->nodeIsPublished($node);
      if ($has_changes) {
        $actions = [
          'preview' => Link::createFromRoute($this->t('Preview changes'), 'entity.node.latest_version', ['node' => $node->id()]),
          'published' => Link::createFromRoute($this->t('See published role'), 'entity.node.canonical', ['node' => $node->id()], ['attributes' => ['target' => '_blank']]),
          'unpublish' => Link::createFromRoute($this->t('Unpublish role'), 'gla_provider.dashboard_opportunity_unpublish', ['node' => $node->id()]),
        ];
      }
      else {
        $actions = [
          'delete' => Link::createFromRoute($this->t('Delete role'), 'gla_provider.dashboard_archive_delete_form', ['node' => $node->id(), 'action' => 'delete'])->toString(),
          'archive' => Link::createFromRoute($this->t('Archive role'), 'gla_provider.dashboard_archive_delete_form', ['node' => $node->id(), 'action' => 'archive'])->toString(),
        ];
      }
    }

    // Check if we should show the duplication option here.
    $duplication_option = $this->roleCanBeDuplicated($node);

    // Set the actions for the page.
    if ($duplication_option) {
      $actions = [
        'duplicate' => Link::createFromRoute($this->t('Duplicate this role'), 'gla_provider.dashboard_opportunity_duplicate_overview', ['node' => $node->id()])->toString(),
      ] + $actions;
    }

    // Getting the published and expired dates from the node.
    $dates = [
      'expired' => $node->get('field_end_of_ad')->value !== 'none' ? $node->get('field_end_of_ad_specific')->value : $this->t('Ongoing'),
      'published' => $this->providerProcessor->datePublished($node),
    ];
    $organisation = $this->providerProcessor->getProviderProfileFromEntity($node);

    $feedback_message = $this->providerProcessor->getLatestFeedback($node);
    if ($feedback_message && isset($feedback_message['log'])) {
      $feedback_message = $feedback_message['log'];
    }

    return [
      '#theme' => 'gla_provider__opportunity_check',
      '#back_button' => Link::createFromRoute($this->t('Back'), 'gla_provider.dashboard_opportunities', [], ['attributes' => ['class' => 'link link--chevron-back']])->toString(),
      '#title' => $node->getTitle(),
      '#steps' => $grouped_steps,
      '#continue' => $moderation_form,
      '#available_actions' => $actions,
      '#dates' => $dates,
      '#has_changes' => $has_changes,
      '#feedback' => ['#markup' => $this->providerProcessor->latestRevisionHasFeedback($node) ? $feedback_message : FALSE],
      '#organisation' => $organisation ? $organisation->getTitle() : '',
      '#back' => Link::createFromRoute($this->t('Back'), 'gla_provider.dashboard_opportunities', [], ['attributes' => ['class' => 'link link--chevron-back']])->toString(),
      '#cache' => [
        'tags' => [
          'node:' . $node->id(),
        ],
      ],
    ];
  }

  /**
   * Check if this node should have the option to be duplicated.
   *
   * @param $node
   * @return array
   */
  public function roleCanBeDuplicated(Node $node) {

    // The node passed into this function is the latest revision. We need to
    // check against the default revision as this is what's being duplicated.
    $default_rev = $this->entityTypeManager->getStorage('node')->load($node->id());

    // Check based on the default revision's state.
    $moderation_state = $default_rev->get('moderation_state')->value;
    switch ($moderation_state) {
      // Content has been approved and is either waiting for scheduled publish
      // or has already been published.
      case 'published':
      case 'approved':
        return TRUE;

      // Content can be archived/unpublished from any state so we need to check
      // that the node had been published at one point and that the validation
      // passes.
      case 'unpublished':
      case 'archived':
        $has_or_had_been_published = !$this->providerProcessor->isFirstTimePublished($default_rev);

        // Validation checks.
        // Do a quick entity validate check here to see if there are any missing fields.
        /** @var \Drupal\Core\Entity\EntityConstraintViolationList $violations[] */
        $violations = $default_rev->validate();
        $violations->filterByFieldAccess($this->currentUser());

        $scheduler_fields = [
          'unpublish_on',
          'publish_on',
          'unpublish_state',
          'publish_state',
          'moderation_state',
        ];

        $errors = 0;
        foreach ($violations->getFieldNames() as $field_name) {
          // Handle the scheduler validation separately.
          if (in_array($field_name, $scheduler_fields)) {
            continue;
          }

          $errors++;
        }

        // If there are validation errors, don't allow duplication otherwise
        // they'll be there too.
        if ($has_or_had_been_published && !$errors) {
          return TRUE;
        }
        else {
          return FALSE;
        }

      // Other states:
      // If default revision is 'draft' or 'ready_for_review', then the node has
      // never transitioned into a default state (i.e. live), so there is no
      // version that can be duplicated.
      default:
        return FALSE;
    }
  }

  /**
   * Controller function for the overview of the duplicate functionality.
   *
   * @param $node
   * @return array
   */
  public function duplicateOpportunityOverview($node) {

    return [
      '#theme' => 'gla_provider__duplicate_overview',
      '#title' => $node->getTitle(),
      '#link' => Link::createFromRoute($this->t('Get Started'), 'gla_provider.dashboard_opportunity_duplicate', ['node' => $node->id()])->toString(),
      '#cache' => [
        'tags' => [
          'node:' . $node->id(),
        ],
      ],
    ];
  }

  /**
   * Controller endpoint for duplicating roles.
   *
   * @param $node
   * @return array
   */
  public function duplicateOpportunity(Node $node) {

    // Create a duplicate, unset the needed fields and redirect to edit node.
    $duplicate = $node->createDuplicate();
    $duplicate->set('title', ' ');
    $duplicate->set('field_start_of_ad', NULL);
    $duplicate->set('field_start_of_ad_specific', NULL);
    $duplicate->set('field_end_of_ad', NULL);
    $duplicate->set('field_end_of_ad_specific', NULL);
    $duplicate->setPublished(FALSE);
    $duplicate->set('moderation_state', 'draft');
    $duplicate->save();

    // Add the duplicate to the provider group.
    $group = $this->providerProcessor->getGroupFromEntity($node);
    $group->addContent($duplicate, 'group_node:opportunity');

    $redirect_url = Url::fromRoute('entity.node.edit_form', ['node' => $duplicate->id()], ['query' => ['provider-duplicate' => 1]])->toString();
    $redirect = new RedirectResponse($redirect_url);
    $redirect->send();

  }

  /**
   * Controller endpoint for the checking if the duplicated node has everything correct.
   *
   * @param $node
   */
  public function duplicateCheck($node) {

    $data = $this->opportunityDataExtractForChecking($node, '');

    // Get the moderation state change form to trigger the 'ready for review' transition.
    // It is altered in gla_provider.module.
    $moderation_form = $this->formBuilder->getForm('\Drupal\content_moderation\Form\EntityModerationForm', $node);

    return [
      '#theme' => 'gla_provider__duplicate_check',
      '#back_button' => Link::createFromRoute($this->t('Back'), 'gla_provider.dashboard_opportunities', [], ['attributes' => ['class' => 'link link--chevron-back']])->toString(),
      '#title' => $node->getTitle(),
      '#steps' => array_slice($data, 0, 2),
      '#continue' => $moderation_form,
      '#duplicate_text' => $this->t('Please check the details of the duplicated role and confirm to create the duplicate role'),
      '#cache' => [
        'tags' => [
          'node:' . $node->id(),
        ],
      ],
    ];
  }

  /**
   * Success page for the duplicate a node functionality.
   *
   * @param $node
   * @return array
   */
  public function duplicateSuccess($node) {
    // Links to add to the page.
    $links = [
      Link::createFromRoute($this->t('View your duplicated role'), 'gla_provider.dashboard_opportunity_edit', ['node' => $node->id()])->toString(),
      Link::createFromRoute($this->t('Return to dashboard'), 'gla_provider.dashboard_opportunities')->toString(),
    ];
    return [
      '#theme' => 'gla_provider__action_success',
      '#action_title' => $this->t('Role Duplicated'),
      '#title' => isset($node) ? $node->getTitle() : '',
      '#links' => $links,
      '#description_text' => $this->t('You will need to submit this role for approval by a Team London administrator before it is published on the website'),
    ];
  }

  /**
   * Controller function for building the archive or delete form.
   *
   * @param $node
   * @param $action
   * @return array
   */
  public function providerOpportunityDeleteArchiveForm($node, $action) {
    $form = $this->formBuilder->getForm('Drupal\gla_provider\Form\ArchiveRoleForm', $node);
    $page_title = $this->t('Archive Role');
    if ($action == 'delete') {
      $form = $this->formBuilder->getForm('Drupal\gla_provider\Form\DeleteRoleForm', $node);
      $page_title = $this->t('Delete Role');
    }

    return [
      '#theme' => 'gla_provider__dashboard_delete_archive_form',
      '#action' => $action,
      '#form' => $form,
      '#page_title' => $page_title,
      '#node_title' => $node->getTitle(),
      '#back' => Link::createFromRoute($this->t('Back'), 'gla_provider.dashboard_opportunity_edit', ['node' => $node->id()], ['attributes' => ['class' => 'link link--chevron-back']])->toString(),
    ];
  }

  /**
   * Controller function for the success page of archive or delete.
   *
   * @param $action
   * @return array
   */
  public function providerOpportunityDeleteOrArchive($action) {
    $node_id = $this->request->query->get('id');
    if ($node_id) {
      $node = $this->entityTypeManager->getStorage('node')->load($node_id);
    }
    // Set the Title of the page depending on action.
    if ($action == 'archived') {
      $action_title = $this->t('Role archived');
    }
    elseif ($action == 'delete') {
      $action_title = $this->t('Role deleted');
    }
    elseif ($action == 'unpublished') {
      $action_title = $this->t('Role Unpublished');
    }
    $links[] = Link::createFromRoute($this->t('Return to dashboard'), 'gla_provider.dashboard_opportunities')->toString();
    return [
      '#theme' => 'gla_provider__action_success',
      '#action_title' => isset($action_title) ? $action_title : '',
      '#title' => isset($node) ? $node->getTitle() : '',
      '#links' => $links,
      '#description_text' => NULL,
    ];
  }

  /**
   * Overview of the change role option.
   *
   * @param $node
   * @return array
   */
  public function changeRoleOverview($node) {
    $button_attributes = ['attributes' => ['class' => 'button button--alt']];
    return [
      '#theme' => 'gla_provider__change_role_overview',
      '#back' => Link::createFromRoute($this->t('Back'), 'gla_provider.dashboard_opportunities', [], ['attributes' => ['class' => 'link link--chevron-back']])->toString(),
      '#title' => $node->getTitle(),
      '#link' => Link::createFromRoute($this->t('Change this role'), 'gla_provider.dashboard_opportunity_edit', ['node' => $node->id()], $button_attributes)->toString(),
      '#see_published' => Link::createFromRoute($this->t('See published role'), 'entity.node.canonical', ['node' => $node->id()], $button_attributes)->toString(),
      '#cache' => [
        'tags' => [
          'node:' . $node->id(),
        ],
      ],
    ];
  }

}
