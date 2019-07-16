<?php

namespace Drupal\gla_opportunity\Entity;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\user\UserInterface;

/**
 * Defines the Application form element entity.
 *
 * @ingroup gla_opportunity
 *
 * @ContentEntityType(
 *   id = "application_form_element",
 *   label = @Translation("Application form element"),
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\gla_opportunity\ApplicationFormElementListBuilder",
 *     "views_data" = "Drupal\gla_opportunity\Entity\ApplicationFormElementViewsData",
 *
 *     "form" = {
 *       "default" = "Drupal\gla_opportunity\Form\ApplicationFormElementForm",
 *       "add" = "Drupal\gla_opportunity\Form\ApplicationFormElementForm",
 *       "edit" = "Drupal\gla_opportunity\Form\ApplicationFormElementForm",
 *       "delete" = "Drupal\gla_opportunity\Form\ApplicationFormElementDeleteForm",
 *     },
 *     "access" = "Drupal\gla_opportunity\ApplicationFormElementAccessControlHandler",
 *     "route_provider" = {
 *       "html" = "Drupal\gla_opportunity\ApplicationFormElementHtmlRouteProvider",
 *     },
 *   },
 *   base_table = "application_form_element",
 *   admin_permission = "administer application form element entities",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "name",
 *     "uuid" = "uuid",
 *     "uid" = "user_id",
 *     "langcode" = "langcode",
 *     "status" = "status",
 *   },
 *   links = {
 *     "canonical" = "/admin/structure/application_form_element/{application_form_element}",
 *     "add-form" = "/admin/structure/application_form_element/add",
 *     "edit-form" = "/admin/structure/application_form_element/{application_form_element}/edit",
 *     "delete-form" = "/admin/structure/application_form_element/{application_form_element}/delete",
 *     "collection" = "/admin/structure/application_form_element",
 *   },
 *   field_ui_base_route = "application_form_element.settings"
 * )
 */
class ApplicationFormElement extends ContentEntityBase implements ApplicationFormElementInterface {

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
   * Get the form field type.
   */
  public function getType() {
    return $this->get('field_type')->value;
  }

  /**
   * Set the form field type.
   */
  public function setType($type) {
    $this->set('field_type', $type);
    return $this;
  }

  /**
   * Get the form field options.
   */
  public function getOptions() {
    $options = [];

    foreach ($this->get('field_field_options') as $key_value_pair) {
      $key = $key_value_pair->entity->get('field_key')->getString();
      $value = $key_value_pair->entity->get('field_value')->getString();
      $options[$key] = $value;
    }

    return $options;
  }

  /**
   * Set the form field options.
   */
  public function setOptions($options) {
    $this->set('field_field_options', $options);
    return $this;
  }

  /**
   * Return the render array for the field for use in a form.
   */
  public function fieldRenderArray($options = []) {
    $type = $this->getType();
    $name = $this->getName();

    $build = [
      '#type' => $type,
      '#title' => $name,
    ];

    $properties = $this->formElementConfiguration('properties', $type);
    foreach ($properties as $property) {
      $default = $this->formElementPropertyDefaults($property);
      $build[$property] = $default;
    }

    // Merge in specified options. These take priority.
    $build = array_merge($build, $options);

    return $build;
  }

  /**
   * Return the render array for the field for use in a preview.
   */
  public function fieldPreviewRenderArray() {
    return $this;
  }

  /**
   * @param $info
   * @param null $type
   *
   * @return array
   */
  protected function formElementConfiguration($info, $type = NULL) {

    $form_element_types = [
      'checkboxes' => [
        'name' => t('Checkboxes'),
        'properties' => [
          '#options',
        ],
      ],
      'select' => [
        'name' => t('Select'),
        'properties' => [
          '#options',
        ],
      ],
      'textarea' => [
        'name' => t('Long text'),
        'properties' => [],
      ],
      'textfield' => [
        'name' => t('Short text'),
        'properties' => [],
      ],
    ];

    $extract = [];
    if ($type && isset($form_element_types[$type], $form_element_types[$type][$info])) {
      $extract = $form_element_types[$type][$info];
    }
    else {
      foreach ($form_element_types as $type => $details) {
        if (isset($details[$info])) {
          $extract[$type] = $details[$info];
        }
      }
    }

    return $extract;
  }

  /**
   * @param $property
   *
   * @return bool|mixed
   */
  protected function formElementPropertyDefaults($property) {

    $property_defaults = [
      '#options' => $this->getOptions(),
      '#value' => '',
    ];

    if (isset($property_defaults[$property])) {
      return $property_defaults[$property];
    }

  }

  /**
   * Return the value to be stored in the serialised array.
   */
  public function valueToStore($value) {

    $value_to_store = FALSE;
    switch ($this->getType()) {
      case 'select':
        // The value we get from the form is the key. Store the option value too.
        $options = $this->getOptions();
        if (isset($options[$value])) {
          $value_to_store = [$value => $options[$value]];
        }
        break;
      case 'checkboxes':
        // The value we get from the form is the key. Store the option value too.
        $value_to_store = [];
        $options = $this->getOptions();
        foreach ($value as $val) {
          if ($val !== 0 && isset($options[$val])) {
            $value_to_store[$val] = $options[$val];
          }
        }
        break;

      case 'textarea':
      case 'textfield':
      default:
        // Nothing extra needed here.
        $value_to_store = $value;
        break;
    }

    return $value_to_store;
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['user_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Authored by'))
      ->setDescription(t('The user ID of author of the Application form element entity.'))
      ->setRevisionable(TRUE)
      ->setSetting('target_type', 'user')
      ->setSetting('handler', 'default');

    $fields['status'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Publishing status'))
      ->setDescription(t('A boolean indicating whether the Application form element is published.'))
      ->setDefaultValue(TRUE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time that the entity was created.'));

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time that the entity was last edited.'));

    // Form field label.
    $fields['name'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Label'))
      ->setDescription(t('The field label.'))
      ->setSettings([
        'max_length' => 200,
        'text_processing' => 0,
      ])
      ->setDefaultValue('')
      ->setDisplayOptions('view', [
        'label' => 'hidden',
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

    return $fields;
  }

}
