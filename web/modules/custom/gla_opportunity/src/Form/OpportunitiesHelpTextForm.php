<?php

namespace Drupal\gla_opportunity\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Form\FormBuilderInterface;

/**
 * Configure example settings for this site.
 */
class OpportunitiesHelpTextForm extends ConfigFormBase {

  /**
   * The form builder.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected $formBuilder;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The entity field manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityFieldManager;

  /**
   * Constructs a \Drupal\system\ConfigFormBase object.
   * @param \Drupal\Core\Form\FormBuilderInterface $form_builder
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   */
  public function __construct(FormBuilderInterface $form_builder, EntityFieldManagerInterface $entity_field_manager, EntityTypeManagerInterface $entity_type_manager, ConfigFactoryInterface $config_factory) {
    parent::__construct($config_factory);
    $this->formBuilder = $form_builder;
    $this->entityFieldManager = $entity_field_manager;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('form_builder'),
      $container->get('entity_field.manager'),
      $container->get('entity_type.manager'),
      $container->get('config.factory')
    );
  }

  /** @var string Config settings */
  const SETTINGS = 'gla_opportunity.help_text_settings';

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'help_text_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      static::SETTINGS,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config(static::SETTINGS);

    $fields = $this->entityFieldManager->getFieldDefinitions('node', 'opportunity');

    // Loading the form object to build the form.
    $node = $this->entityTypeManager->getStorage('node')->create(['type' => 'opportunity']);
    $node_form = $this->entityTypeManager->getFormObject('node', 'default')->setEntity($node);

    $opportunity_form = $this->formBuilder->getForm($node_form);

    // Go through the available fields attached to node and create the field.
    foreach ($fields as $index => $item) {
      // Make sure that the field is a step.
      if (stripos($index, 'step_') === 0) {
        $form[$index] = [
          '#type' => 'textarea',
          '#title' => $this->t($item->getLabel() . ' help text'),
          '#default_value' => $config->get($index),
          '#weight' => $opportunity_form[$index]['#weight']
        ];
        $form[$index . '_link'] = [
          '#type' => 'url',
          '#title' => $this->t($item->getLabel() . ' Link'),
          '#default_value' => $config->get($index . '_link'),
          '#weight' => $opportunity_form[$index]['#weight']
        ];
      }
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    // Get form values and config.
    $values = $form_state->getValues();
    $config = $this->configFactory()->getEditable(static::SETTINGS);

    // Foreach field before the submit, save the value.
    foreach ($values as $index => $item) {
      if ($index == 'submit') {
        break;
      }
      $config->set($index, $item);
    }
    $config->save();

    parent::submitForm($form, $form_state);
  }
}
