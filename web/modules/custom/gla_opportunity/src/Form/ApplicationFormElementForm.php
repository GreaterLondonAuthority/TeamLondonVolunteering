<?php

namespace Drupal\gla_opportunity\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form controller for Application form element edit forms.
 *
 * @ingroup gla_opportunity
 */
class ApplicationFormElementForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    /* @var $entity \Drupal\gla_opportunity\Entity\ApplicationFormElement */
    $form = parent::buildForm($form, $form_state);

    // Hide the field_key field and auto-populate it. We do this so that users' answers aren't changed if admins re-order the options.
    if (isset($form['field_field_options'], $form['field_field_options']['widget'])) {
      foreach ($form['field_field_options']['widget'] as $key => &$item) {
        if (!is_int($key)) {
          continue;
        }

        if (empty($item['subform']['field_key']['widget'][0]['value']['#default_value'])) {
          $item['subform']['field_key']['widget'][0]['value']['#default_value'] = 'option_' . ($key + 1);
        }

        $item['subform']['field_key']['#disabled'] = 'disabled';
      }
    }

    // Ajaxify the field options.
    $form['field_field_options']['#states'] = [
      'visible' => [
        'input[name="field_type"]' => [
          ['value' => 'checkboxes'],
          ['value' => 'select'],
        ],
      ],
    ];

//    // Preview.
//    $render_array = $this->entity->fieldRenderArray();
//
//    $form['preview'] = $render_array;
//    $form['preview']['#weight'] = 100;
//    $form['preview']['#disabled'] = 'disabled';
//
//    $form['preview'] = [
//      '#type' => 'fieldset',
//      '#title' => 'Preview',
//      '#disabled' => 'disabled',
//      '#weight' => 100,
//      'form_element' => $render_array,
//    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $entity = $this->entity;

    $status = parent::save($form, $form_state);

    switch ($status) {
      case SAVED_NEW:
        drupal_set_message($this->t('Created the %label Application form element.', [
          '%label' => $entity->label(),
        ]));
        break;

      default:
        drupal_set_message($this->t('Saved the %label Application form element.', [
          '%label' => $entity->label(),
        ]));
    }
    $form_state->setRedirect('entity.application_form_element.canonical', ['application_form_element' => $entity->id()]);
  }

}
