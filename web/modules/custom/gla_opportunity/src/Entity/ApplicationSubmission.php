<?php

namespace Drupal\gla_opportunity\Entity;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\node\Entity\Node;
use Drupal\user\UserInterface;

/**
 * Defines the Application submission entity.
 *
 * @ingroup gla_opportunity
 *
 * @ContentEntityType(
 *   id = "application_submission",
 *   label = @Translation("Application submission"),
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\gla_opportunity\ApplicationSubmissionListBuilder",
 *     "views_data" = "Drupal\gla_opportunity\Entity\ApplicationSubmissionViewsData",
 *
 *     "form" = {
 *       "default" = "Drupal\gla_opportunity\Form\ApplicationSubmissionForm",
 *       "add" = "Drupal\gla_opportunity\Form\ApplicationSubmissionForm",
 *       "edit" = "Drupal\gla_opportunity\Form\ApplicationSubmissionForm",
 *       "delete" = "Drupal\gla_opportunity\Form\ApplicationSubmissionDeleteForm",
 *     },
 *     "access" = "Drupal\gla_opportunity\ApplicationSubmissionAccessControlHandler",
 *     "route_provider" = {
 *       "html" = "Drupal\gla_opportunity\ApplicationSubmissionHtmlRouteProvider",
 *     },
 *   },
 *   base_table = "application_submission",
 *   admin_permission = "administer application submission entities",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "name",
 *     "uuid" = "uuid",
 *     "uid" = "user_id",
 *     "langcode" = "langcode",
 *     "status" = "status",
 *   },
 *   links = {
 *     "canonical" = "/admin/structure/application_submission/{application_submission}",
 *     "add-form" = "/admin/structure/application_submission/add",
 *     "edit-form" = "/admin/structure/application_submission/{application_submission}/edit",
 *     "delete-form" = "/admin/structure/application_submission/{application_submission}/delete",
 *     "collection" = "/admin/structure/application_submission",
 *   },
 *   field_ui_base_route = "application_submission.settings"
 * )
 */
class ApplicationSubmission extends ContentEntityBase implements ApplicationSubmissionInterface {

  use EntityChangedTrait;

  /**
   * {@inheritdoc}
   */
  public static function preCreate(EntityStorageInterface $storage_controller, array &$values) {
    parent::preCreate($storage_controller, $values);
    $values += [
      'user_id' => \Drupal::currentUser()->id(),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getName() {
    return $this->get('name')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setName($name) {
    $this->set('name', $name);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getCreatedTime() {
    return $this->get('created')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setCreatedTime($timestamp) {
    $this->set('created', $timestamp);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getOwner() {
    return $this->get('user_id')->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function getOwnerId() {
    return $this->get('user_id')->target_id;
  }

  /**
   * {@inheritdoc}
   */
  public function setOwnerId($uid) {
    $this->set('user_id', $uid);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setOwner(UserInterface $account) {
    $this->set('user_id', $account->id());
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function isPublished() {
    return (bool) $this->getEntityKey('status');
  }

  /**
   * {@inheritdoc}
   */
  public function setPublished($published) {
    $this->set('status', $published ? TRUE : FALSE);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function isSubmitted() {
    return (bool) $this->getEntityKey('submitted');
  }

  /**
   * {@inheritdoc}
   */
  public function setSubmitted($submitted) {
    $this->set('submitted', $submitted ? TRUE : FALSE);
    $this->set('submitted_timestamp', time());
    return $this;
  }

  /**
   * @return \Drupal\node\Entity\Node
   */
  public function getOpportunityNode() {
    return $this->get('node_id')->entity;
  }

  /**
   * Check if submissions for this opportunity need the additional questions.
   */
  public function showAdditionalQuestions() {
    $additional_questions = [
      'field_tell_us_why' => 'field_include_qu1',
      'field_special_requirements' => 'field_include_qu2',
    ];

    $node = $this->getOpportunityNode();

    $result = [];
    foreach ($additional_questions as $submission_field => $opp_field) {
      $result[$submission_field] = FALSE;
      if ($node && $node->hasField($opp_field)) {
        $field_value = $node->get($opp_field)->getValue();
        if (!empty($field_value) && $field_value[0]['value'] == 'yes') {
          // Show this question.
          $result[$submission_field] = TRUE;
        }
      }
    }

    return $result;
  }

  /**
   * Generates the text to be sent in the email.
   */
  public function generateEmailText(Node $opp_node) {

    // Add info about the opportunity.
    $opp_title = $opp_node->getTitle();
    $opp_url = $opp_node->toUrl('canonical', ['absolute' => TRUE])->toString();

    $email_text = "
Role title: $opp_title
View role: $opp_url
";

    // Get the 2 standard questions.
    $email = $this->get('field_email')->getString();
    $email_label = $this->get('field_email')->getFieldDefinition()->getLabel();
    $name = $this->get('field_first_name')->getString() . ' ' . $this->get('field_last_name')->getString();

    $email_text .= "
$email_label: $email
Full name: $name";

    // Check the two extra questions.
    $additional_questions = $this->showAdditionalQuestions();
    foreach ($additional_questions as $field_name => $show) {
      if ($show) {
        $label = $this->get($field_name)->getFieldDefinition()->getLabel();
        $value = $this->get($field_name)->getString();

        $email_text .= "
$label: $value";
      }
    }

    return $email_text;
  }

  /**
   * {@inheritdoc}
   */
  public function delete() {
    // Send an email to the user if this application is accepted/not responded.
    if (!$this->get('responded')->value || $this->get('field_application_status')->value == 'accepted') {
      // todo: finalise email
      $mail_manager = \Drupal::service('plugin.manager.mail');
      $module = 'gla_opportunity';
      $langcode = \Drupal::currentUser()->getPreferredLangcode();

      $to = $this->get('field_email')->value;
      $first_name = $this->get('field_first_name')->value;
      $last_name = $this->get('field_last_name')->value;
      $volunteer_name = $first_name . ' ' . $last_name;

      $params['title'] = t('Team London volunteering role deleted');
      $params['message'] = t('Dear @volunteer_name,', ['@volunteer_name' => $volunteer_name]);
      $result = $mail_manager->mail($module, 'application_deleted', $to, $langcode, $params, NULL, TRUE);
    }

    parent::delete();
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['user_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Authored by'))
      ->setDescription(t('The user ID of author of the Application submission entity.'))
      ->setRevisionable(TRUE)
      ->setSetting('target_type', 'user')
      ->setSetting('handler', 'default')
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'weight' => -1,
        'settings' => [
          'match_operator' => 'CONTAINS',
          'size' => '60',
          'placeholder' => '',
        ],
      ]);

    $fields['status'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Publishing status'))
      ->setDescription(t('A boolean indicating whether the Application submission is published.'))
      ->setDefaultValue(TRUE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time that the entity was created.'));

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time that the entity was last edited.'));

    $fields['name'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Submission label'))
      ->setDescription(t('The name of the Application submission entity.'))
      ->setSettings([
        'max_length' => 200,
        'text_processing' => 0,
      ])
      ->setDefaultValue('')
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => 0,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => 0,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE)
      ->setRequired(TRUE);

    $fields['node_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Opportunity node'))
      ->setDescription(t('The nid of the opportunity this submission is for.'))
      ->setSetting('target_type', 'node')
      ->setSetting('handler', 'default')
      ->setSetting('handler_settings', ['target_bundles' => ['opportunity' => 'opportunity']])
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'settings' => [
          'match_operator' => 'CONTAINS',
          'size' => 60,
          'placeholder' => '',
        ],
        'weight' => -2,
      ]);

    $fields['submitted'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Submitted'))
      ->setDescription(t('A boolean indicating whether the Application submission has been submitted.'))
      ->setDefaultValue(FALSE)
      ->setDisplayOptions('form', [
        'type' => 'boolean_checkbox',
        'weight' => -2,
      ]);

    $fields['submitted_timestamp'] = BaseFieldDefinition::create('timestamp')
      ->setLabel(t('Submitted time'))
      ->setDescription(t('The time that the entity was submitted.'))
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => 0,
      ]);

    $fields['responded'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Responded'))
      ->setDescription(t('A boolean indicating whether the Application submission has been responded to.'))
      ->setDefaultValue(FALSE)
      ->setDisplayOptions('form', [
        'type' => 'boolean_checkbox',
        'weight' => -2,
      ]);

    return $fields;
  }

}
