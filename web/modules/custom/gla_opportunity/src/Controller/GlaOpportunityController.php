<?php

namespace Drupal\gla_opportunity\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityFormBuilder;
use Drupal\Core\Link;
use Drupal\file\Entity\File;
use Drupal\gla_provider\ProviderProcessor;
use Drupal\node\Entity\Node;
use Drupal\taxonomy\Entity\Term;
use Drupal\user\Entity\User;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Class GlaOpportunityController.
 */
class GlaOpportunityController extends ControllerBase {

  /**
   * @var ProviderProcessor
   */
  protected $providerProcessor;

  /**
   * @var EntityFormBuilder
   */
  protected $entityFormBuilder;

  /**
   * Constructs a GlaOpportunityController object.
   *
   * @param ProviderProcessor $provider_processor
   * @param EntityFormBuilder $entity_form_builder
   */
  public function __construct(ProviderProcessor $provider_processor, EntityFormBuilder $entity_form_builder) {
    $this->providerProcessor = $provider_processor;
    $this->entityFormBuilder = $entity_form_builder;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('gla_provider.processor'),
      $container->get('entity.form_builder')
    );
  }

  /**
   * The _controller for gla_opportunity.new.
   */
  public function createNewOpp() {
    $current_user = \Drupal::currentUser();
    $user = User::load($current_user->id());

    // Make sure this provider user has been approved (i.e. has a group).
    $group = $this->providerProcessor->getGroup($user);
    if (!$group) {
      drupal_set_message(t('Your provider profile must be approved by GLA before you can add opportunities.'), 'error');
      throw new AccessDeniedHttpException();
    }

    // Create an opportunity and redirect to its overview page.
    // We do this because of the multistep forms - more consistent with a saved
    // entity.
    $opportunity_nid = $this->providerProcessor->createStubOpportunity($user);
    return $this->redirect('gla_opportunity.opportunity_overview', ['node' => $opportunity_nid]);
  }

  /**
   * The _controller for gla_opportunity.saved.
   */
  public function saved(Node $node) {

    // Get the profile link for this user.
    $user = $node->getOwner();
    $profile_nid = $this->providerProcessor->getUserProviderProfile($user);

    // Generate links.
    $draft_opportunities = Link::createFromRoute(t('Manage your roles'), 'gla_provider.dashboard_opportunities');
    $create_new = Link::createFromRoute(t('Create a new volunteering role'), 'gla_opportunity.new');
    $return_to_profile = Link::createFromRoute(t('Return to my profile page'), 'gla_provider.application_overview', ['node' => $profile_nid]);
    $read_more = Link::createFromRoute(t('Read more about Team London'), '<front>');
    $dashboard = Link::createFromRoute(t('Your dashboard'), 'gla_provider.dashboard');

    return [
      '#theme' => 'gla_opportunity__saved',
      '#draft_opportunities' => $draft_opportunities,
      '#create_new' => $create_new,
      '#return_to_profile' => $return_to_profile,
      '#read_more' => $read_more,
      '#dashboard' => $dashboard,
      '#cache' => [
        'tags' => [
          'node:' . $node->id(),
        ],
      ],
    ];
  }

  /**
   * The _controller for gla_opportunity.opportunity_overview.
   */
  public function initialOverview(Node $node) {
    $data = $this->opportunityDataExtract($node, 'sections');

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
    $continue = Link::createFromRoute(t('Start now'), 'entity.node.edit_form', ['node' => $node->id()], [
      'attributes' => [
        'class' => [
          'button',
          'button--alt',
        ],
      ],
    ]);

    $build = [
      '#theme' => 'gla_opportunity__initial_overview',
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
   * The sections for the overview is broken into.
   */
  public function applicationInitialOverviewSections() {
    $sections = [];

    // Opportunity overview.
    $sections['opp_overview'] = [
      'title',
      'field_start_of_ad',
      'field_dates_needed',
      'field_monday',
      'field_bullet_1',
      'field_postcode',
    ];

    // Opportunity details.
    $sections['opp_details'] = [
      'field_causes_supported',
      'field_type_options',
      'field_what_skills_useful',
      'field_what_skills_gain',
      'field_what_bullet_1',
      'field_what_change_bullet_1',
    ];

    // Additional information.
    $sections['additional'] = [
      'field_what_training_bullet_1',
      'field_minimum_age',
      'field_cover_expenses',
      'field_bg_checks_required',
      'field_image',
      'field_additional_bullet_1',
    ];

    // Questions for volunteers (optional).
    $sections['volunteer_questions'] = [
      'field_include_qu1',
      'field_include_qu2',
    ];

    return $sections;
  }

  /**
   * Function to deal with logic around the date fields.
   *
   * @param Node $node
   * @param $field
   * @param $field_name
   * @return bool|string
   */
  private function checkIfDateFieldIsNeeded(Node $node, $field_name, $field_value) {
    $field_key_value = $node->get($field_name)->value;
    if (empty($field_key_value)) {
      return $field_value;
    }
    if ($field_key_value == 'specific' || $field_key_value == 'one_off') {
      return FALSE;
    }
    if ($field_key_value == 'none') {
      return $this->t('To: Role Ongoing');
    }
    if (strpos($field_name, 'start') !== FALSE && $field_key_value !== 'asap') {
      return $this->t("From: $field_key_value");
    }
    if (strpos($field_name, 'end') !== FALSE) {
      return $this->t("To: $field_key_value");
    }
    return $field_value;
  }

  /**
   * The special cases to check what fields need to be rendered and what don't.
   *
   * @param Node $node
   * @param $field
   * @param $field_name
   * @param $field_value
   * @param $node_form
   * @return bool|\Drupal\Core\StringTranslation\TranslatableMarkup
   */
  private function checkIfOtherFieldsAreNeeded(Node $node, $field_name, $field_value, $node_form) {

    // Going through each custom field and extracting the needed data.
    switch ($field_name) {
      case 'field_minimum_age':
        $field_key_value = $node->get($field_name)->value;
        return $field_key_value == 'other' ? FALSE : $field_value;
      case 'field_cover_expenses':
        $field_key_value = $node->get($field_name)->value;
        return $field_key_value == 'specific' ? FALSE : $field_value;
      case 'field_type_options':
        $field_key_value = $node->get($field_name)->value;
        return $field_key_value == 'other' || $field_key_value == 'type' ? FALSE : $field_value;
      case 'field_type_options_type':
        $field_options = explode(', ' , $field_value);
        if (!empty($field_options)) {
          foreach ($field_options as $option) {
            if (isset($node_form['field_type_options_type']['widget'][$option])) {
              $item_to_add = $node_form['field_type_options_type']['widget'][$option]['#title'];
              $options[] = $item_to_add->__toString();
            }
          }
        }
        return isset($options) ? implode(', ', $options) : $field_value;
      case 'field_include_qu1':
        $question = 'Question: Why do you want to register your interest in this role?';
        $field_key_value = $node->get($field_name)->value;
        return $field_key_value == 'yes' ? $this->t("$question Included") : $this->t("$question Not Included");
      case 'field_include_qu2':
        $question = 'Question: Do you have any access requirements or other special requirements we should be aware of?';
        $field_key_value = $node->get($field_name)->value;
        return $field_key_value == 'yes' ? $this->t("$question Included") : $this->t("$question Not Included");
      case 'field_image':
        $field_target_id = $node->get($field_name)->target_id;
        return !empty($field_target_id) ? $this->t('File Uploaded') : $field_value;
      default:
        return $field_value;
    }
  }

  /**
   * Extract the data we need.
   */
  public function opportunityDataExtractForChecking(Node $node, $type) {

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

    $days_of_week = [
      'field_monday' => 'Monday',
      'field_tuesday' => 'Tuesday',
      'field_wednesday' => 'Wednesday',
      'field_thursday' => 'Thursday',
      'field_friday' => 'Friday',
      'field_saturday' => 'Saturday',
      'field_sunday' => 'Sunday',
    ];

    // Array of date fields that need to be processed.
    $date_fields = [
      'field_end_of_ad',
      'field_end_of_ad_specific',
      'field_start_of_ad',
      'field_start_of_ad_specific',
      'field_one_off_date',
      'field_ongoing_end_date',
      'field_ongoing_start_date',
      'field_dates_needed',
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

        elseif (in_array($key, array_keys($days_of_week))) {
          // Get label names.
          $time_values = [];
          foreach ($field_value as $val) {
            $time_key = $val['value'];
            if (isset($node_form[$key]['widget'][$time_key])) {
              $time_label = $node_form[$key]['widget'][$time_key]['#title'];
              $time_values[] = $time_label;
            }
          }

          $field_value = implode(', ', $time_values);
        }
        else {
          $field_value = trim($node->$key->getString());
        }

        if (empty($field_value)) {
          $field_value = t('To be completed');
        }
        elseif (isset($node_form[$key]['widget'], $node_form[$key]['widget']['#options'], $node_form[$key]['widget']['#options'][$field_value])) {
          // Get label for list options.
          $field_value = $node_form[$key]['widget']['#options'][$field_value];
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

        // Get the link to change the answer. On the sections screen this is the step title. 'Change' on the check answers view.
        $link_classes = 'link link--edit';
        $link_title = t('Change');
        if ($type == 'sections') {
          $link_title = strip_tags($label);
          $link_classes = '';
        }
        $query = [
          'step' => $step_num,
        ];
        if (gla_provider_in_dashboard_overview_flow() == 'edit') {
          $query += ['provider-edit' => 1];
        }
        if (gla_provider_in_dashboard_overview_flow() == 'duplicate') {
          $query += ['provider-duplicate' => 1];
        }
        $edit_link = Link::createFromRoute($link_title, 'entity.node.edit_form', ['node' => $node->id()], [
          'query' => $query,
          'attributes' => [
            'class' => $link_classes,
          ],
        ]);

        $field_value = $this->checkIfOtherFieldsAreNeeded($node, $key, $field_value, $node_form);

        if (in_array($key, $date_fields)) {
          $field_value = $this->checkIfDateFieldIsNeeded($node, $key, $field_value);
          if (!$field_value) {
            continue;
          }
        }

        if (array_key_exists($key, $days_of_week)) {
          $field_value_string = $field_value;
          if (!is_string($field_value_string) && method_exists($field_value, '__toString')) {
            $field_value_string = $field_value->__toString();
          }

          if ($field_value_string != 'To be completed') {
            $field_value = $days_of_week[$key] . ': ' . $field_value;
          }
        }

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
    }

    // Go through and tidy up superfluous 'to be completed' values.
    foreach ($data as $step_num => $data_values) {
      $numeric_keys = array_filter(array_keys($data_values), 'is_numeric');

      // If there's only one result then move on.
      $num_values = count($numeric_keys);
      if ($num_values < 2) {
        continue;
      }

      $empty = 0;
      foreach ($numeric_keys as $numeric_key) {
        $value = $data_values[$numeric_key]['value'];
        $value_string = $value;
        if (!is_string($value_string) && method_exists($value, '__toString')) {
          $value_string = $value->__toString();
          if ($value_string == 'To be completed') {
            $empty++;
            // There are other values so remove this unless they're all empty
            // and this is the last one.
            if ($empty != $num_values) {
              unset($data[$step_num][$numeric_key]);
            }
          }
        }
      }
    }

    // Sort by weight the outer and inner arrays.
    uasort($data, function ($a, $b) { return ($a['#weight'] < $b['#weight']) ? -1 : 1; });
    foreach ($data as $key => $inner) {
      uasort($inner, function ($a, $b) {
        if (is_array($a) && is_array($b)) {
          return ($a['#weight'] < $b['#weight']) ? -1 : 1;
        }
      });
      $data[$key] = $inner;
    }

    return $data;
  }

  /**
   * Extract the data we need.
   */
  public function opportunityDataExtract(Node $node, $type) {

    // Load the node entity edit form.
    $node_form = $this->entityFormBuilder->getForm($node, 'default');

    // Sort through form to get step for each question.
    $step_map = [];
    $using_step_fields = FALSE;
    foreach ($node_form['#steps'] as $step_num => $step_data) {
      foreach ($step_data->children as $child) {
        if (substr($child, 0, 5) !== 'step_') {
          $step_map[$child] = $step_num;
          if ($type != 'sections') {
            break;
          }
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
    ];

    // Extract the values to display and generate the edit link.
    $data = [];
    foreach ($node_form as $key => $value) {
      if ($node->hasField($key) && isset($step_map[$key])) {
        $step_num = $step_map[$key];

        $field_value = $node->$key->getValue();
        if (isset($field_value[0], $field_value[0]['target_id'])) {
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
        elseif (in_array($key, $address_fields)) {
          // Address fields need to be grouped together.
          $address_values = [];
          foreach ($address_fields as $address_field) {
            $val = $node->$address_field->getString();
            if ($val) {
              $address_values[] = $val;
            }

            $field_value = implode(', ', $address_values);
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
        elseif (isset($node_form[$key]['widget'], $node_form[$key]['widget']['#options'], $node_form[$key]['widget']['#options'][$field_value])) {
          // Get label for list options.
          $field_value = $node_form[$key]['widget']['#options'][$field_value];
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

        $data[$key] = [
          'label' => $label,
          'value' => $field_value,
          'link' => $edit_link,
          '#weight' => $value['#weight'],
        ];
      }
    }

    // Sort by weight.
    uasort($data, function ($a, $b) { return ($a['#weight'] < $b['#weight']) ? -1 : 1; });

    return $data;
  }

  /**
   * The _controller for gla_opportunity.opportunity_check.
   */
  public function opportunityCheck(Node $node) {

    $data = $this->opportunityDataExtractForChecking($node, 'answer_check');

    // Get the moderation state change form to trigger the 'ready for review' transition.
    // It is altered in gla_provider.module.
    $moderation_form = \Drupal::formBuilder()->getForm('\Drupal\content_moderation\Form\EntityModerationForm', $node);

    return [
      '#theme' => 'gla_opportunity__opportunity_check',
      '#steps' => $data,
      '#continue' => $moderation_form,
      '#cache' => [
        'tags' => [
          'node:' . $node->id(),
        ],
      ],
    ];
  }
}
