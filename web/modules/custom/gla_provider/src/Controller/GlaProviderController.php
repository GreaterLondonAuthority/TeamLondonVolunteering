<?php

namespace Drupal\gla_provider\Controller;

use Drupal\content_moderation\ModerationInformation;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityFormBuilder;
use Drupal\Core\Link;
use Drupal\file\Entity\File;
use Drupal\gla_provider\ProviderProcessor;
use Drupal\group\Entity\Group;
use Drupal\node\Entity\Node;
use Drupal\taxonomy\Entity\Term;
use Drupal\user\Entity\User;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\views\Views;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Class GlaProviderController.
 */
class GlaProviderController extends ControllerBase {

  /**
   * @var EntityFormBuilder
   */
  protected $entityFormBuilder;

  /**
   * @var ProviderProcessor
   */
  protected $providerProcessor;

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
   * @var \Drupal\content_moderation\ModerationInformation
   */
  protected $moderationInformation;

  /**
   * Constructs a GlaOpportunityController object.
   *
   * @param EntityFormBuilder $entity_form_builder
   * @param ProviderProcessor $provider_processor
   */
  public function __construct(EntityFormBuilder $entity_form_builder, ProviderProcessor $provider_processor, RequestStack $request, EntityTypeManagerInterface $entity_type_manager, ModerationInformation $moderation_information) {
    $this->entityFormBuilder = $entity_form_builder;
    $this->providerProcessor = $provider_processor;
    $this->request = $request->getCurrentRequest();
    $this->entityTypeManager = $entity_type_manager;
    $this->moderationInformation = $moderation_information;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity.form_builder'),
      $container->get('gla_provider.processor'),
      $container->get('request_stack'),
      $container->get('entity_type.manager'),
      $container->get('content_moderation.moderation_information')
    );
  }

  /**
   * Helper function.
   */
  public function getName(User $user) {
    return $user->get('field_first_name')->getString() . ' ' . $user->get('field_last_name')->getString();
  }

  /**
   * The _controller for gla_provider.start.
   */
  public function start() {
    $user = \Drupal::currentUser();
    $roles = $user->getRoles();
    $uid = $user->id();
    if ($uid && in_array('provider', $roles)) {
      // If logged in and a provider, get the nid of this user's provider profile page.
      $nodes = \Drupal::entityTypeManager()
        ->getStorage('node')
        ->loadByProperties(['uid' => $uid]);
      if ($node = reset($nodes)) {
        return $this->redirect('gla_provider.application_overview', ['node' => $node->id()]);
      }
    }

    // If user is logged out, go to the register page.
    return $this->redirect('multiple_registration.role_registration_page', ['rid' => 'volunteer']);
  }

  /**
   * The _controller for gla_provider.dashboard.
   */
  public function dashboard() {
    $user = \Drupal::currentUser();
    $roles = $user->getRoles();
    if (!$user->id() || !in_array('provider', $roles)) {
      // If user is logged out, go to the register page.
      return $this->redirect('multiple_registration.role_registration_page', ['rid' => 'provider']);
    }

    $uid = $user->id();
    $user = User::load($uid);
    $name = $this->getName($user);
    $group_id = 0;
    $profile_nid = $this->providerProcessor->getUserProviderProfile($user);
    $group = $this->providerProcessor->getGroup($user);
    if ($group) {
      $group_id = $this->providerProcessor->getGroup($user)->id();
    }

    // Check what the 'Edit organisation profile' link should be.
    // If the profile has been submitted for review link to the node
    // view as the provider cannot make changes at this time.
    $edit_profile_route_name = 'gla_provider.application_overview';
    if ($profile_nid) {
      $provider_profile = Node::load($profile_nid);
      if ($provider_profile) {
        $latest_rev = $this->providerProcessor->loadLatestRevision($provider_profile);
        $moderation_state = $latest_rev->get('moderation_state')->value;
        if ($moderation_state == 'ready_for_review') {
          $edit_profile_route_name = 'entity.node.canonical';
          if ($this->moderationInformation->hasPendingRevision($provider_profile)) {
            $edit_profile_route_name = 'entity.node.latest_version';
          }
        }
      }
    }
    else {
      $profile_nid = 0;
    }

    $items = [
      'volunteer_roles' => [
        [
          t('Create a new role'),
          'gla_opportunity.new',
          t(''),
        ],
        [
          t('Manage your roles'),
          'gla_provider.dashboard_opportunities',
          t(''),
        ],
      ],
      'volunteer_interest' => [
        [
          t('View volunteer interest by role'),
          'gla_provider.volunteer_interest',
          t(''),
        ],
        [
          t('View all volunteers'),
          'view.applications.all_applications',
          t(''),
          ['group' => $group_id],
        ],
      ],
      'your_organisation' => [
        [
          t('Manage your members'),
          'view.provider_group_members.page_1',
          t(''),
          ['group' => $group_id],
        ],
        [
          t('Your organisation profile'),
          'entity.node.canonical',
          t(''),
          ['node' => $profile_nid],
        ],
      ],
      'account_settings' => [
        [
          t('Edit organisation profile'),
          $edit_profile_route_name,
          t(''),
          ['node' => $profile_nid, 'dashboard' => 1],
        ],
        [
          t('Delete my account'),
          'gla_user.delete_account_provider',
          t(''),
        ],
      ],
    ];

    $links = [];
    foreach ($items as $key => $key_items) {
      foreach ($key_items as $item) {
        // Generate the link.
        $title = $item[0];
        $route = $item[1];
        $description = $item[2];
        $params = ['user' => $uid];
        if (isset($item[3])) {
          $params += $item[3];
        }

        $link = Link::createFromRoute($title, $route, $params);
        $links[$key][] = [
          'link' => $link,
          'description' => $description,
        ];
      }
    }

    // Setting the in params for this view so that pulls in everything in group with params.
    $to_do_view = Views::getView('provider_to_do');
    $input = $this->request->query->all();
    if ($group_id) {
      $to_do_view->setDisplay('page_1');
      $default_args = ['group' => $group_id];
    }
    else {
      $to_do_view->setDisplay('no_group');
      $default_args = ['user' => $user->id()];
    }
    $arguments = $default_args;
    if (!empty($input)) {
      $arguments = $default_args + $input;
    }
    $to_do_view->setArguments($arguments);
    $to_do_view->pager = new \Drupal\views\Plugin\views\pager\Some([], '', []);
    $to_do_view->pager->init($to_do_view, $to_do_view->display_handler);
    $to_do_view->pager->setItemsPerPage(10);
    $to_do_view->display_handler->setOption('use_more', 1);
    $to_do_view->display_handler->setOption('use_more_always', 1);
    $to_do_view->display_handler->setOption('use_more_text', 'See all action required');

    // Setting the default group on the view.
    $updates_view = Views::getView('provider_updates');
    if ($group_id) {
      $updates_view->setDisplay('page_1');
    }
    else {
      $updates_view->setDisplay('no_group');
    }
    $updates_view->setArguments($default_args);
    $updates_view->pager = new \Drupal\views\Plugin\views\pager\Some([], '', []);
    $updates_view->pager->init($to_do_view, $to_do_view->display_handler);
    $updates_view->pager->setItemsPerPage(10);
    $updates_view->display_handler->setOption('use_more', 1);
    $updates_view->display_handler->setOption('use_more_always', 1);
    $updates_view->display_handler->setOption('use_more_text', 'See all updates');

    return [
      '#theme' => 'gla_provider__dashboard',
      '#name' => $name,
      '#links' => $links,
      '#to_do_view' => $to_do_view->render(),
      '#updates_view' => $updates_view->render(),
      '#cache' => [
        'contexts' => [
          'user'
        ],
        'tags' => [
          'user:' . $user->id(),
        ],
      ],
    ];
  }

  /**
   * Controller function for the overview of volunteer interest.
   */
  public function volunteerInterest() {

    // Load the profile node and get necessary variables.
    $uid = $this->currentUser()->id();
    $user = User::load($uid);
    $profile_nid = $this->providerProcessor->getUserProviderProfile($user);
    $provider_profile = $this->entityTypeManager->getStorage('node')->load($profile_nid);
    $group = $this->providerProcessor->getGroup($user);

    // Setting the default group on the view.
    $roles_to_respond_to = Views::getView('provider_roles_response');
    $roles_to_respond_to->setDisplay('roles_to_respond_to');
    $roles_to_respond_to->setArguments(['group' => $group->id()]);
    $roles_responded_to = Views::getView('provider_roles_response');
    $roles_responded_to->setDisplay('provider_roles_complete');
    $roles_responded_to->setArguments(['group' => $group->id()]);

    return [
      '#theme' => 'gla_provider__volunteer_interest',
      '#back' => Link::createFromRoute($this->t('Back'), 'gla_provider.dashboard', [], ['attributes' => ['class' => 'link link--chevron-back']]),
      '#organisation' => $provider_profile->getTitle(),
      '#roles_to_respond_to' => $roles_to_respond_to->render(),
      '#roles_responded_to' => $roles_responded_to->render(),
      '#cache' => [
        'contexts' => [
          'user',
        ],
        'tags' => [
          'user:' . $user->id(),
        ],
      ],
    ];
  }

  /**
   * The sections for the overview is broken into.
   */
  public function applicationInitialOverviewSections() {
    $sections = [];

    // Organisation contact details.
    $sections['organisation_contact'] = [
      'title',
      'field_building_and_street',
      'field_uk_telephone_number',
    ];

    // Organisation online.
    $sections['organisation_online'] = [
      'field_organisation_web_address',
      'field_organisation_facebook',
      'field_organisation_twitter',
      'field_organisation_instagram',
    ];

    // Describe your organisation.
    $sections['organisation_description'] = [
      'field_what_bullet_1',
      'field_organisation_deliver_value',
      'field_organisation_who_benefits',
      'field_organisation_tags',
    ];

    // Supply images for your organisation.
    $sections['organisation_images'] = [
      'field_organisation_logo',
      'field_organisation_image',
    ];

    // Subscribe to newsletter.
    $sections['organisation_newsletter'] = [
      'field_organisation_newsletter',
    ];

    return $sections;
  }

  /**
   * Extract the data we need.
   */
  public function providerProfileDataExtract(Node $node, $type) {

    // Load the node entity edit form.
    $node_form = $this->entityFormBuilder->getForm($node, 'default');

    // Sort through form to get step for each question.
    $step_map = [];
    $using_step_fields = FALSE;
    foreach ($node_form['#steps'] as $step_num => $step_data) {
      foreach ($step_data->children as $child) {
        if (substr($child, 0, 5) !== 'step_') {
          $step_map[$child] = $step_num;
        }
        else {
          $using_step_fields = TRUE;
        }
      }
    }

    // Address fields need to be grouped together.
    $address_fields = [
      'field_building_and_street',
      'field_building_and_street_2',
      'field_building_and_street_3',
      'field_town_or_city',
      'field_postcode',
      'field_borough',
    ];

    // Extract the values to display and generate the edit link.
    $data = [];
    foreach ($node_form as $key => $value) {
      if ($node->hasField($key) && isset($step_map[$key])) {
        $step_num = $step_map[$key];

        $field_value = $node->$key->getValue();

        // Show full address.
        $address_step = FALSE;

        if (in_array($key, $address_fields)) {
          // Address fields need to be grouped together.
          $address_values = [];

          foreach ($address_fields as $address_field) {

            // For plain text address fields.
            if ($address_field !== 'field_borough') {
              $val = $node->$address_field->getString();
              if ($val) {
                $address_values[] = $val;

              }
            }

            // If borough field.
            else {
              // Set val as term name.
              $field_val_array = $node->$address_field->getValue();
              if (isset($field_val_array[0], $field_val_array[0]['target_id'])) {
                $tid = $field_val_array[0]['target_id'];
                $term = Term::load($tid);
                if ($term) {
                  $address_values[] = $term->getName();
                }
              }
            }

            $field_value = implode(', ', $address_values);
          }
          $address_step = TRUE;
        }


        elseif (isset($field_value[0], $field_value[0]['target_id'])) {
          $term_field_values = [];
          foreach ($field_value as $val) {
            // Get the term names.
            $tid = $val['target_id'];
            $term = Term::load($tid);
            if ($term) {
              $term_field_values[] = $term->getName();
            }
          }

          $field_value = implode(', ', $term_field_values);
        }
        elseif ($key == 'field_organisation_newsletter') {
          // Change the wording for newsletter.
          if (isset($field_value[0], $field_value[0]['value'])) {
            if ($field_value[0]['value'] == 1) {
              $field_value = t('Yes');
            }
            else {
              $field_value = t('No');
            }
          }
          else {
            $field_value = t('To be completed');
          }
        }
        else {
          $field_value = trim($node->$key->getString());
        }

        if (empty($field_value)) {
          $field_value = t('To be completed');
        }
        elseif ($type == 'sections') {
          // Value is filled in.
          $field_value = t('Completed');
        }

        // Grab title.
        if ($using_step_fields) {
          // We want to show the step title in the output.
          $step_field_name = $node_form['#steps'][$step_num]->children[0];
          $label = $node_form[$step_field_name]['widget']['#title'];
        }
        else {
          // use the field's title.
          $label = $value['widget']['#title'];
        }

        if ($key == 'field_organisation_logo' || $key == 'field_organisation_image') {
          $field_val = $node->$key->getValue();
          if (isset($field_val[0])) {
            $fid = $field_val[0]['target_id'];
            $file = File::load($fid);
            if ($file) {
              $img_url = $file->url();
              $field_value = $img_url;
              // TODO: do we need to display the image?
              $field_value = t('Completed');
            }
          }
        }

        // Get the link to change the answer. On the sections screen this is the step title. 'Change' on the check answers view.
        $link_classes = 'link link--edit';
        $link_title = t('Change');
        if ($type == 'sections') {
          $link_title = strip_tags($label);
          $link_classes = '';
        }
        $edit_link = Link::createFromRoute($link_title, 'entity.node.edit_form', ['node' => $node->id()], [
          'query' => [
            'step' => $step_num,
          ],
          'attributes' => [
            'class' => $link_classes,
          ],
        ]);

        if ($type == 'answer_check') {
          if (!isset($data[$step_num])) {
            $data[$step_num] = [
              'label' => $label,
              'link' => $edit_link,
              '#weight' => $value['#weight'],
              [
                'value' => $field_value,
                '#weight' => $value['#weight'],
              ],
            ];
          }
          elseif (!$address_step) {
            $data[$step_num][] = [
              'value' => $field_value,
              '#weight' => $value['#weight'],
            ];
          }
        }
        else {
          $data[$key] = [
            'label' => $label,
            'value' => $field_value,
            'link' => $edit_link,
            '#weight' => $value['#weight'],
          ];
        }
      }
    }

    // Sort by weight.
    uasort($data, function ($a, $b) { return ($a['#weight'] < $b['#weight']) ? -1 : 1; });

    return $data;
  }

  /**
   * The _controller for gla_provider.application_check.
   */
  public function applicationCheck(Node $node) {

    $data = $this->providerProfileDataExtract($node, 'answer_check');

    // Get the moderation state change form to trigger the 'ready for review' transition.
    // It is altered in gla_provider.module.
    $moderation_form = \Drupal::formBuilder()->getForm('\Drupal\content_moderation\Form\EntityModerationForm', $node);

    return [
      '#theme' => 'gla_provider__application_check',
      '#steps' => $data,
      '#continue' => $moderation_form,
      '#cache' => [
        'tags' => [
          'node:' . $node->id(),
        ],
      ],
    ];
  }

  /**
   * The _controller for gla_provider.application_overview.
   */
  public function applicationInitialOverview(Node $node) {

    $data = $this->providerProfileDataExtract($node, 'sections');

    $field_data = [];
    $sections = $this->applicationInitialOverviewSections();
    // Split the sections up.
    foreach ($sections as $section => $fields) {
      foreach ($fields as $field) {
        if (isset($data[$field])) {
          $field_data[$section][$field] = $data[$field];
        }
      }
    }

    // Get the continue link.
    $button_attributes = ['attributes' => ['class' => 'button button--alt']];
    $continue = Link::createFromRoute(t('Continue'), 'entity.node.edit_form', ['node' => $node->id()], $button_attributes);

    $build = [
      '#theme' => 'gla_provider__initial_overview',
      '#fields' => $field_data,
      '#continue' => $continue,
      '#cache' => [
        'tags' => [
          'node:' . $node->id(),
        ],
      ],
    ];

    return $build;
  }

  /**
   * The _controller for gla_provider.saved.
   */
  public function saved(Node $node) {

    // Generate links.
    $return_to_profile = Link::createFromRoute(t('Return to my profile page'), 'gla_provider.application_overview', ['node' => $node->id()]);
    $read_more = Link::createFromRoute(t('Read more about Team London'), '<front>');

    return [
      '#theme' => 'gla_provider__saved',
      '#return_to_profile' => $return_to_profile,
      '#read_more' => $read_more,
      '#cache' => [
        'tags' => [
          'node:' . $node->id(),
        ],
      ],
    ];
  }

  /**
   * The _controller for gla_provider.user_add_overview.
   */
  public function newMemberOverview(Group $group) {
    return [
      '#theme' => 'gla_provider__new_member_overview',
      '#back' => Link::createFromRoute(t('Back'), 'gla_provider.dashboard', [], ['attributes' => ['class' => 'button button--alt']]),
      '#start' => Link::createFromRoute(t('Start now'), 'gla_provider.user_add', ['group' => $group->id()], ['attributes' => ['class' => 'button button--alt']]),
    ];
  }

  /**
   * The _controller for gla_provider.member_created.
   */
  public function newMemberCreated(Group $group, $email) {
    return [
      '#theme' => 'gla_provider__new_member_created',
      '#email' => $email,
      '#return_to_members' => Link::createFromRoute(t('Return to manage team members'), 'view.provider_group_members.page_1', ['group' => $group->id()]),
      '#return_to_dashboard' => Link::createFromRoute(t('Return to your dashboard'), 'gla_provider.dashboard'),
    ];
  }

  /**
   * The _controller for gla_provider.user_view.
   */
  public function memberView(Group $group, User $user) {
    $form = $this->entityFormBuilder->getForm($user, 'default');
    $provider_profile = $this->providerProcessor->getProviderProfileFromEntity($group);
    $org_name = $provider_profile ? $provider_profile->getTitle() : '';

    // Sort through form to get step for each question.
    $step_map = [];
    foreach ($form['#steps'] as $step_num => $step_data) {
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
          'account',
        ],
      ],
      'full_name' => [
        'label' => t('Full name'),
        'fields' => [
          'field_first_name',
          'field_last_name',
        ],
      ],
    ];

    $storage = [
      'account' => $user->getEmail(),
      'field_first_name' => $user->get('field_first_name')->getString(),
      'field_last_name' => $user->get('field_last_name')->getString(),
    ];

    $data = [];
    $weight = 0;
    foreach ($sections as $key => $info) {
      $fields = $info['fields'];
      $section_values = [];

      // Collect the field values in this section.
      $step_num = FALSE;
      foreach ($fields as $field) {
        if (isset($storage[$field], $step_map[$field])) {
          $step_num = $step_map[$field];
          $section_values[] = trim($storage[$field]);
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
        $step_field_name = $form['#steps'][$step_num]->children[0];
        $label = $form[$step_field_name]['widget']['#title'];
      }

      // Get the link to change the answer.
      $link_classes = 'link link--edit';
      $link_title = t('Change');
      $edit_link = \Drupal\Core\Link::createFromRoute($link_title, 'gla_provider.user_edit', ['group' => $group->id(), 'user' => $user->id()], [
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

    // Links.
    $password_reset = Link::createFromRoute(t('Password reset'), 'gla_provider.password_reset', [
      'group' => $group->id(),
      'user' => $user->id(),
    ]);

    $delete_member = Link::createFromRoute(t('Delete team member'), 'gla_provider.user_delete', [
      'group' => $group->id(),
      'user' => $user->id(),
    ]);

    return [
      '#theme' => 'gla_provider__member_view',
      '#org_name' => $org_name,
      '#password_reset' => $password_reset,
      '#delete_member' => $delete_member,
      '#sections' => $data,
    ];
  }

  /**
   * The _controller for gla_provider.password_reset.
   */
  public function sendPasswordResetLink(Group $group, User $user) {
    // Trigger email and return to member list.
    _user_mail_notify('password_reset', $user);

    $this->messenger()->addStatus($this->t('Password reset link has been sent'));

    return $this->redirect('view.provider_group_members.page_1', ['group' => $group->id()]);
  }

}
