<?php

namespace Drupal\gla_multistep_forms\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Plugin implementation of the 'multistep_separator_widget' widget.
 *
 * @FieldWidget(
 *   id = "multistep_separator_widget",
 *   label = @Translation("Multistep default"),
 *   field_types = {
 *     "multistep_separator"
 *   }
 * )
 */
class MultistepSeparatorWidget extends WidgetBase {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $elements = [];
    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = [];
    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {

    $element['step_title'] = [
      '#markup' => '<h2 class="heading--alt">' . $element['#title'] . '</h2>',
    ];

    $element['step_description'] = [
      '#markup' => '<p>' . $element['#description'] . '</p>',
    ];

    return $element;
  }

}
