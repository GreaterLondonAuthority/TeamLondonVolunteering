<?php

namespace Drupal\gla_site\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class RegistrationFlowSettings
 *
 * Configure the content pages in the registration flows.
 *
 * @package Drupal\gla_site\Form
 */
class RegistrationFlowSettings extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'gla_site_registration_flow_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $settings = $this->config('gla_site.registration_flow_settings');
    $content = $settings->get();
    foreach ($content as $node => $value) {
      $title = str_replace('node:', '', $node);
      $title = str_replace('_', ' ', $title);
      $title = ucwords($title);
      $form[$node] = [
        '#type' => 'textfield',
        '#title' => $title,
        '#size' => 20,
        '#description' => $this->t('The nid of the content.'),
        '#default_value' => $value,
      ];
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    $settings = $this->config('gla_site.registration_flow_settings');
    $content = $settings->get();
    foreach ($content as $node => $prev_value) {
      $value = $form_state->getValue($node);
      if ($value) {
        // Save the value for this item.
        $this->config('gla_site.registration_flow_settings')
          ->set($node, $value)
          ->save();
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'gla_site.registration_flow_settings',
    ];
  }
}
