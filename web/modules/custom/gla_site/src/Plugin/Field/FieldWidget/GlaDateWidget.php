<?php

namespace Drupal\gla_site\Plugin\Field\FieldWidget;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\datetime\Plugin\Field\FieldWidget\DateTimeWidgetBase;

/**
 * Plugin implementation of the 'gla_date' widget.
 *
 * @FieldWidget(
 *   id = "gla_date",
 *   label = @Translation("GLA three input"),
 *   field_types = {
 *     "datetime"
 *   }
 * )
 */
class GlaDateWidget extends DateTimeWidgetBase {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);

    // Wrap all of the number elements with a fieldset.
    $element['#theme_wrappers'][] = 'fieldset';

    // Set up the inputs for each date part.
    $element['day'] = [
      '#title' => t('Day'),
      '#type' => 'number',
      '#min' => 01,
      '#max' => 31,
      '#size' => 2,
      '#element_validate' => [[$this, 'validate']],
      '#prefix' => '<div class="col-sm-3">',
      '#suffix' => '</div>',
    ];

    $element['month'] = [
      '#title' => t('Month'),
      '#type' => 'number',
      '#min' => 01,
      '#max' => 12,
      '#size' => 2,
      '#element_validate' => [[$this, 'validate']],
      '#prefix' => '<div class="col-sm-3">',
      '#suffix' => '</div>',
    ];

    $element['year'] = [
      '#title' => t('Year'),
      '#type' => 'number',
      '#min' => 1900,
      '#max' => 2200,
      '#size' => 4,
      '#element_validate' => [[$this, 'validate']],
      '#prefix' => '<div class="col-sm-6">',
      '#suffix' => '</div>',
    ];

    // Convert the stored date for viewing.
    if ($items[$delta]->date) {
      $date = $items[$delta]->date;
      $element['day']['#default_value'] = $date->format('d');
      $element['month']['#default_value'] = $date->format('m');
      $element['year']['#default_value'] = $date->format('Y');
    }

    // Hide the 'value' element but we massage our values into it after.
    $element['value']['#access'] = FALSE;

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    return parent::settingsForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state) {
    // We need to convert the date from the input elements into the storage
    // timezone and format.
    foreach ($values as &$item) {
      try {
        // Adjust the date for storage.
        $date = DrupalDateTime::createFromArray(['year' => $item['year'], 'month' => $item['month'], 'day' => $item['day']]);
        $format = DateTimeItemInterface::DATE_STORAGE_FORMAT;
        $item['value'] = $date->format($format);
      }
      catch (\Exception $e) {
        // Invalid date or no date given.
        if (empty($item['year']) && empty($item['month']) && empty($item['day'])) {
          // No date given - ignore.
        }
        else {
          $item['value'] = FALSE;
        }
      }
    }

    return $values;
  }

  /**
   * Validate the date field.
   */
  public function validate($element, FormStateInterface $form_state) {

    // Get the path to the values.
    $date_element_path = $element['#parents'];
    array_pop($date_element_path);

    // Ensure this is a valid date.
    $values = NestedArray::getValue($form_state->getValues(), $date_element_path);
    // Only validate if we have values.
    if (empty($values['year']) && empty($values['month']) && empty($values['day'])) {
      return;
    }

    try {
      $date = DrupalDateTime::createFromArray(['year' => $values['year'], 'month' => $values['month'], 'day' => $values['day']]);
    }
    catch (\Exception $e) {
      $form_state->setError($element, t('You have input an invalid date. Please check again.'));
    }
  }
}
