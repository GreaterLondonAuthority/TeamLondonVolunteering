<?php

namespace Drupal\gla_volunteer\Controller;

use Drupal\Core\Config\Entity\ThirdPartySettingsInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityFormBuilder;
use Drupal\Core\Link;
use Drupal\taxonomy\Entity\Term;
use Drupal\user\Entity\User;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\Entity;
use Drupal\gla_provider\ProviderProcessor;

/**
 * Class GlaVolunteerController.
 */
class GlaVolunteerController extends ControllerBase {

  /**
   * @var EntityFormBuilder
   */
  protected $entityFormBuilder;

  /**
   * @var ProviderProcessor
   */
  protected $providerProcessor;

  /**
   * Constructs a GlaOpportunityController object.
   *
   * @param EntityFormBuilder $entity_form_builder
   */
  public function __construct(EntityFormBuilder $entity_form_builder, ProviderProcessor $provider_processor) {
    $this->entityFormBuilder = $entity_form_builder;
    $this->providerProcessor = $provider_processor;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity.form_builder'),
      $container->get('gla_provider.processor')
    );
  }

  /**
   * Helper function.
   */
  public function getName(User $user) {
    return $user->get('field_first_name')->getString() . ' ' . $user->get('field_last_name')->getString();
  }

  /**
   * The _controller for gla_volunteer.start.
   */
  public function start() {
    $user = \Drupal::currentUser();
    $roles = $user->getRoles();
    if ($user->id() && in_array('volunteer', $roles)) {
      // If logged in and a volunteer, redirect to their equal opportunities settings page.
      return $this->redirect('gla_volunteer.equal_opportunities', ['user' => $user->id()]);
    }

    // If user is logged out, go to the register page.
    return $this->redirect('multiple_registration.role_registration_page', ['rid' => 'volunteer']);
  }

  /**
   * The _controller for gla_volunteer.dashboard.
   */
  public function dashboard() {
    $user = \Drupal::currentUser();
    $roles = $user->getRoles();
    if (!$user->id() || !in_array('volunteer', $roles)) {
      // If user is logged out, go to the register page.
      return $this->redirect('multiple_registration.role_registration_page', ['rid' => 'volunteer']);
    }

    $uid = $user->id();
    $user = User::load($uid);
    $name = $this->getName($user);

    // Check if the profile is started/completed.
    $profile_status = $this->getVolunteerProfileStatus($user);
    if ($profile_status === 1) {
      // Profile complete if all fields are answered.
      $show_info = FALSE;
      $continue_link_title = '';
    }
    elseif ($profile_status > 0) {
      // Profile started if any field is answered.
      $show_info = TRUE;
      $continue_link_title = t('Continue');
    }
    else {
      // Profile not started.
      $show_info = TRUE;
      $continue_link_title = t('Get started');
    }

    $continue_link = Link::createFromRoute($continue_link_title, 'gla_volunteer.preferences_overview', ['user' => $uid], ['attributes' => ['class' => ['button button--alt']]]);

    // Fetch all the current users applications with user id.
    $applications = \Drupal\views\Views::getView('applications');
    $applications->setDisplay('to_do');
    $applications->setArguments(['user' => $uid]);

    // Add cache tags for any applications belonging to the user.
    $application_submissions = $this->entityTypeManager()->getStorage('application_submission')->loadByProperties([
      'user_id' => $uid,
    ]);

    $cache_tags = [
      'user:' . $user->id(),
    ];

    if (!empty($application_submissions)) {
      foreach ($application_submissions as $application_submission) {
        $cache_tags[] = 'application_submission:' . $application_submission->id();
      }
    }

    return [
      '#theme' => 'gla_volunteer__dashboard',
      '#name' => $name,
      '#show_info' => $show_info,
      '#continue_link' => $continue_link,
      '#links' => $this->createVolunteerSidebar(),
      '#applications' => $applications->render(),
      '#cache' => [
        'contexts' => [
          'user',
        ],
        'tags' => $cache_tags,
      ],
    ];
  }

  /**
   * Helper function to create the volunteer sidebar.
   */
  private function createVolunteerSidebar($field_to_exclude = NULL) {
    $sections = [
      'Your volunteer roles' => [
        [
          t('Find a volunteer role'),
          'view.profile_search.page_1',
          t('Search and find a volunteer opportunity'),
        ],
        [
          t('Manage your volunteer roles'),
          'view.applications.to_do',
          t('The roles where you have registered interest'),
        ],
      ],
      'Your volunteer profile' => [
        [
          t('Update your volunteer profile'),
          'gla_volunteer.preferences_overview',
          t('Review and update your profile details'),
        ],
        [
          t('Update your equal opportunities information'),
          'gla_volunteer.equal_opportunities',
          t('Review and update your equal opportunities information'),
        ],
      ],
      'You account settings' => [
        [
          t('Update your personal details'),
          'gla_volunteer.edit_account_overview',
          t('Review and update your account details'),
        ],
        [
          t('Newsletter updates'),
          'entity.webform.canonical',
          t('Manage your subscription to our newsletter'),
        ],
      ],
    ];

    $links = [];
    foreach ($sections as $key => $section) {
      foreach ($section as $item) {
        // Generate the link.
        $title = $item[0];
        if (!is_null($title) && $title == $field_to_exclude) {
          continue;
        }
        $route = $item[1];
        $description = $item[2];
        if ($route == 'entity.webform.canonical') {
          $link = Link::createFromRoute($title, $route, ['webform' => 'newsletter_sign_up']);
        }
        else {
          $link = Link::createFromRoute($title, $route, ['user' => $this->currentUser()->id()]);
        }
        $links[$key][] = [
          'link' => $link,
          'description' => $description,
        ];
      }
    }
    return $links;
  }

  /**
   * Works on the unique fields that need custom logic around them.
   *
   * Many more can be added.
   *
   * @param $form
   * @param $field_name
   * @param $field_value
   * @return bool
   */
  public function equalOpportunitiesUniqueField($form, $field_name, $field_value) {
    switch ($field_name) {
      case 'field_gender':
        // Retunrs the value of gender other field here.
        return $form['field_gender_other']['widget'][0]['value']['#value'];
      default:
        return FALSE;
    }
  }

  /**
   * The _controller for gla_volunteer.equal_opportunities_check.
   */
  public function equalOpportunitiesCheck(User $user) {
    // Load the user entity form, using our custom profile form mode.
    // Use entity edit form mode to save on duplication of views etc.
    $user_form = $this->entityFormBuilder->getForm($user, 'equal_opportunities');

    // Sort through form to get step for each question.
    $step_map = [];
    foreach ($user_form['#steps'] as $step_num => $step_data) {
      $child = $step_data->children[0];
      $step_map[$child] = $step_num;
    }

    // Extract the values to display and generate the edit link.
    $data = [];
    foreach ($user_form as $key => $value) {
      if ($user->hasField($key) && isset($step_map[$key])) {
        $step_num = $step_map[$key];
        // Get the link to change the answer.
        $edit_link = Link::createFromRoute(t('Change'), 'gla_volunteer.equal_opportunities', ['user' => $user->id()], [
          'attributes' => [
            'class' => [
              'link link--edit',
            ],
          ],
          'query' => [
            'step' => $step_num,
          ],
        ]);

        $field_value = $user->$key->getValue();
        if (isset($field_value[0], $field_value[0]['target_id'])) {
          // Get the term names.
          $tid = $field_value[0]['target_id'];
          $term = Term::load($tid);
          if ($term) {
            $field_value = $term->getName();
          }
          else {
            // Fallback.
            $field_value = $user->$key->getString();
          }
        }
        elseif ($key == 'field_tandc') {
          // Change the wording for T&Cs.
          if (isset($field_value[0], $field_value[0]['value']) && $field_value[0]['value'] == 1) {
            $field_value = t('Accepted');
          }
          else {
            $field_value = t('To be completed');
          }
        }
        else {
          $field_value = $user->$key->getString();
        }

        // Check for edge case fields here.
        $field_value_already_set = 0;
        if ($field_value == 'other') {
          $field_value_change = $this->equalOpportunitiesUniqueField($user_form, $key, $field_value);
          if ($field_value_change) {
            $field_value = $field_value_change;
            $field_value_already_set = 1;
          }
        }

        // Converts select list keys to the corresponding values. Makes sure they are saved as
        // values rather than entity references.
        if (!$field_value_already_set && isset($user_form[$key]['widget']['#options'], $user_form[$key]['widget']['#options'][$field_value]) && $user_form[$key]['widget']['#key_column'] == 'value') {
          $field_value = $user_form[$key]['widget']['#options'][$field_value]->__toString();
        }

        if (empty($field_value)) {
          $field_value = t('To be completed');
        }

        $data[] = [
          'label' => $value['widget']['#title'],
          'value' => $field_value,
          'link' => $edit_link,
          '#weight' => $value["#weight"],
        ];
      }
    }

    // Sort by weight.
    usort($data, function ($a, $b) { return ($a['#weight'] < $b['#weight']) ? -1 : 1; });

    // Add the continue link as a form so we can validate the responses.
    $continue = \Drupal::formBuilder()->getForm('\Drupal\gla_volunteer\Form\UserSubmitForm', $user);

    return [
      '#theme' => 'gla_volunteer__profile_check',
      '#steps' => $data,
      '#continue' => $continue,
      '#cache' => [
        'tags' => [
          'user:' . $user->id(),
        ],
      ],
    ];
  }

  /**
   * Get user data from form.
   */
  public function extractUserAnswers(User $user, $type, $form_mode) {
    // Load the user entity form, using our custom profile form mode.
    // Use entity edit form mode to save on duplication of views etc.
    $user_form = $this->entityFormBuilder->getForm($user, $form_mode);

    // Sort through form to get step for each question.
    $step_map = [];
    $using_step_fields = FALSE;
    foreach ($user_form['#steps'] as $step_num => $step_data) {
      foreach ($step_data->children as $child) {
        if (substr($child, 0, 5) !== 'step_') {
          $step_map[$child] = $step_num;
          break;
        }
        else {
          $using_step_fields = TRUE;
        }
      }
    }

    // Extract the values to display and generate the edit link.
    $data = [];
    foreach ($user_form as $key => $value) {
      if (($key == 'account' || $user->hasField($key)) && isset($step_map[$key])) {
        $step_num = $step_map[$key];
        if ($key == 'account') {
          $field_value = $user->getEmail();
        }
        elseif ($key == 'field_loc_london_borough' || $key == 'field_loc_postcode' || $key == 'field_location') {
          // Display the detail answer as well as the option chosen.
          $field_value = '';
          $detail_value = '';
          $option_chosen = $user->get('field_location')->getString();
          if (isset($user_form['field_location']['widget']['#options'][$option_chosen])) {
            $option_label = $user_form['field_location']['widget']['#options'][$option_chosen]->__toString();
            $detail_field = 'field_loc_' . $option_chosen;
            if ($user->hasField($detail_field)) {
              $field_values = $user->$detail_field->getValue();
              // If Borough selected.
              if (isset($field_values[0], $field_values[0]['target_id'])) {
                // Get value as term id.
                $detail_tid = $user->get($detail_field)->getString();
                // Get term name from term id.
                $detail_term = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->load($detail_tid);
                if ($detail_term) {
                  $detail_value = $detail_term->getName();
                }
              }
              // If Postcode selected.
              else {
                $detail_value = $user->get($detail_field)->getString();
              }
            }

            // Output field value with label if required.
            switch($option_chosen) {
              case 'dont_mind':
                $field_value = $option_label;
                break;
              default:
                $field_value = "$option_label: $detail_value";
                break;
            }
          }
        }
        elseif ($key == 'field_first_name' && is_volunteer_account_overview_route()) {
          $first_name = $user->get('field_first_name')->value;
          $last_name = $user->get('field_last_name')->value;
          $field_value = $first_name . ' ' . $last_name;
        }
        else {
          $field_values = $user->$key->getValue();
          if (isset($field_values[0], $field_values[0]['target_id'])) {
            // Get the term names.
            $field_value = [];
            foreach ($field_values as $item) {
              $tid = $item['target_id'];
              $term = Term::load($tid);
              if ($term) {
                $field_value[] = $term->getName();
              }
            }
          }
          else {
            $field_value = $user->$key->getString();
          }
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
          $step_field_name = $user_form['#steps'][$step_num]->children[0];
          $label = $user_form[$step_field_name]['widget']['#title'];
        }
        else {
          // Use the field's title.
          $label = $value['widget']['#title'];
        }

        // Get the link to change the answer. On the sections screen this is the step title. 'Change' on the check answers view.
        $link_title = t('Change');
        if ($type == 'sections') {
          $link_title = strip_tags($label);
        }

        $route_name = 'gla_volunteer.preferences';
        if ($form_mode == 'default') {
          $route_name = 'entity.user.edit_form';
        }
        $edit_link = Link::createFromRoute($link_title, $route_name, ['user' => $user->id()], [
          'query' => [
            'step' => $step_num,
          ],
          'attributes' => [
            'class' => 'link--edit'
          ],
        ]);

        $data[$key] = [
          'label' => $label,
          'value' => $field_value,
          'link' => $edit_link,
          '#weight' => $value["#weight"],
        ];
      }
    }

    // Sort by weight.
    uasort($data, function ($a, $b) { return ($a['#weight'] < $b['#weight']) ? -1 : 1; });

    return $data;
  }

  /**
   * The _controller for gla_volunteer.preferences_intro.
   */
  public function preferencesIntro(User $user) {

    // Get the continue link.
    $continue = Link::createFromRoute(t('Continue'), 'gla_volunteer.preferences', ['user' => $user->id()]);

    return [
      '#theme' => 'gla_volunteer__preferences_intro',
      '#continue' => $continue,
      '#cache' => [
        'tags' => [
          'user:' . $user->id(),
        ],
      ],
    ];
  }

  /**
   * The _controller for gla_volunteer.preferences_check.
   */
  public function preferencesCheck(User $user) {

    $data = $this->extractUserAnswers($user, 'answer_check', 'volunteer_interests');

    // Get the continue link.
    $continue = Link::createFromRoute(t('Submit and continue'), 'gla_volunteer.dashboard', [], ['attributes' => ['class' => ['button button--alt']]]);

    return [
      '#theme' => 'gla_volunteer__profile_check',
      '#steps' => $data,
      '#continue' => $continue,
      '#cache' => [
        'tags' => [
          'user:' . $user->id(),
        ],
      ],
    ];
  }

  /**
   * The _controller for gla_volunteer.edit_account_overview.
   */
  public function editAccountOverview(User $user) {
    $data = $this->extractUserAnswers($user, 'answer_check', 'default');
    $data['field_first_name']['label'] = t('Name');
    $data['account']['label'] = t('Email');
    $data['password'] = $data['account'];
    $data['password']['label'] = t('Password');
    $data['password']['value'] = '';
    $data['password']['link'] = Link::createFromRoute(t('Change'), 'entity.user.edit_form', ['user' => $user->id()], [
      'query' => [
        'step' => 3,
      ],
      'attributes' => [
        'class' => 'link--edit'
      ],
    ]);

    // Get the continue link.
    $continue = Link::createFromRoute(t('Your volunteer account'), 'gla_volunteer.dashboard');

    return [
      '#theme' => 'gla_volunteer__profile_check',
      '#steps' => $data,
      '#continue' => $continue,
      '#cache' => [
        'tags' => [
          'user:' . $user->id(),
        ],
      ],
    ];
  }

  /**
   * Extract the data we need for application view.
   */
  public function submissionDataExtract(Entity $entity) {

    // The steps needed for the check page.
    $node_steps = [
      'step_apply_full_name' => [
        'field_first_name' => 1,
        'field_last_name' => 1,
      ],
    ];

    $extra_steps = [
      'step_apply_why' => [
        'field_tell_us_why' => 2,
      ],
      'step_apply_special_requirements' => [
        'field_special_requirements' => 3
      ],
    ];

    // Only add if these optional fields are set.
    $additional_questions = $entity->showAdditionalQuestions();
    foreach ($extra_steps as $step => $field) {
      foreach ($field as $field_name => $weight) {
        if ($additional_questions[$field_name]) {
          $node_steps[$step] = [
            $field_name => $weight,
          ];
        }
      }
    }

    // Load the node entity edit form.
    $node_form = $this->entityFormBuilder->getForm($entity, 'default');

    // Extract the values to display and generate the edit link.
    $data = [];
    foreach ($node_steps as $step_name => $step) {
      $field_value = '';
      foreach ($step as $field_name => $weight) {
        if (empty($field_value)) {
          $field_value = trim($entity->$field_name->value);
        }
        else {
          $field_value .= ' ' . trim($entity->$field_name->value);
        }
      }
      $data[$weight] = [
        'label' => $node_form[$step_name]['widget'][0]['#title'],
        'value' => !empty($field_value) ? $field_value : NULL,
      ];
    }
    return $data;
  }

  /**
   * Controller for gla_volunteer.view_submissions.
   *
   * @param Entity $entity
   */
  public function viewSubmission(Entity $entity) {

    // Get the data to render and the opportunity that is being applied for.
    $data = $this->submissionDataExtract($entity);
    $opportunity = $this->entityTypeManager()->getStorage('node')->load($entity->get('node_id')->target_id);
    $status = $entity->get('field_status')->value;
    // Get the opportunity owner here.
    $opportunity_owner = $opportunity->getOwner();
    $user = User::load($this->currentUser()->id());
    $name = $this->getName($user);
    // Check against the opportunity owner and get the group of the provider.
    if (!empty($opportunity_owner)) {
      $provider_profile_id = $this->providerProcessor->getUserProviderProfile($opportunity_owner);
      $provider_profile = User::load($provider_profile_id);
    }
    $submitted_timestamp = $entity->get('submitted_timestamp')->value;

    $response_text = NULL;
    if (!is_null($status)) {
      switch ($status) {
        case 'accepted':
          // If the provider group is already set - get the latest message.
          if ($entity->hasField('field_response') && !$entity->get('field_response')->isEmpty()) {
            $response_text = $entity->get('field_response')->value;
          }
          else {
            $response_text = $this->t(/** @lang text */
              '<h3 class="heading--alt">Response received:</h3><div class="well well--confirmation">Dear @volunteer_name
              Thank you for your interest in this role.
              We would be delighted if you would speak with us further regarding this role.
              Thank you for taking the time to register your interest. We hope to speak to you soon.
          
              Kind Regards,
              @organisation_name</div>', [
                '@volunteer_name' => $name,
                '@organisation_name' => isset($provider_profile) ? $provider_profile->getTitle() : 'Team London',
              ]);
          }
          break;
        case 'unsuccessful':
          // If the provider group is already set - get the latest message.
          if ($entity->hasField('field_response') && !$entity->get('field_response')->isEmpty()) {
            $response_text = $entity->get('field_response')->value;
          }
          else {
            $response_text = $this->t(/** @lang text */
              '<h3 class="heading--alt">Response received:</h3><div class="well">Dear @volunteer_name
              Thank you for your interest in this role.
              On this occasion you have not been selected for this volunteering role.
              We wish you success in your future volunteering interests.
              
              Kind Regards,
              @organisation_name</div>', [
                '@volunteer_name' => $name,
                '@organisation_name' => isset($provider_profile) ? $provider_profile->getTitle() : 'Team London',
              ]);
          }
          break;
        case 'awaiting_response':
          $response_text = $this->t(/** @lang text */
            '<h3 class=heading--alt>Waiting for response</h3><div class="well">Your interest has been registered with the organisation.
            When a response is given you will be notified via email and the response will appear here.
            
            Date of submission @date_of_submission</div>', [
              '@date_of_submission' => !is_null($submitted_timestamp) ? date('d-m-Y', $submitted_timestamp) : 'none',
            ]);
          break;
      }
    }

    return [
      '#theme' => 'gla_volunteer__view_submission',
      '#back' => Link::createFromRoute($this->t('Back'), 'gla_volunteer.dashboard', [], ['attributes' => ['class' => 'link link--chevron-back']]),
      '#opportunity_link' => Link::createFromRoute($this->t('Open role in new window'), 'entity.node.canonical', ['node' => $opportunity->id()], ['attributes' => ['target' => '_blank']]),
      '#opportunity_title' => $opportunity->getTitle(),
      '#response_text' => $response_text,
      '#data' => $data,
      '#cache' => [
        'tags' => [
          'application_submission:' . $entity->id(),
        ],
      ],
    ];
  }

  /**
   * The sections for the overview is broken into.
   */
  public function volunteerProfileOverviewSections() {
    $sections = [];

    // Types of opportunities.
    $sections['types'] = [
      'field_types_of_opportunity',
    ];

    // Skills to offer.
    $sections['skills_offer'] = [
      'field_skills_to_offer',
    ];

    // Skills to gain.
    $sections['skills_gain'] = [
      'field_skills_to_gain',
    ];

    // Location preferences.
    $sections['loc_pref'] = [
      'field_location',
    ];

    return $sections;
  }

  /**
   * Check the status of the volunteer's profile - how complete it is.
   *
   * Returns an array with 'all' and 'required', giving the percentage of all
   * questions answered and required questions answered, respectively.
   *
   * @return string
   */
  public function getVolunteerProfileStatus(User $user) {

    // Check if we have this data cached.
    $cid = "user_profile_complete:{$user->id()}";
    $cached_data = \Drupal::cache()->get($cid);
    if ($cached_data) {
      return $cached_data->data;
    }

    $profile_fields = [
      'field_types_of_opportunity',
      'field_skills_to_offer',
      'field_skills_to_gain',
      'field_location',
    ];

    $incomplete = 0;
    foreach ($profile_fields as $field_name) {
      // Check if we have a value for this field.
      if ($user->get($field_name)->isEmpty()) {
        // This field is incomplete.
        $incomplete++;
      }
    }

    $profile_status = 1 - ($incomplete / count($profile_fields));

    // Cache this until user is updated.
    $tags = ["user:{$user->id()}"];
    \Drupal::cache()->set($cid, $profile_status, -1, $tags);

    return $profile_status;
  }

}
