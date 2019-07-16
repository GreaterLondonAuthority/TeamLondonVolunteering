<?php

namespace Drupal\gla_opportunity\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Mail\MailManager;
use Drupal\gla_opportunity\Entity\ApplicationSubmission;
use Drupal\node\Entity\Node;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class ApplicationSubmissionSubmitForm
 *
 * Form for submitting and validating applications.
 *
 * @package Drupal\gla_opportunity\Form
 */
class ApplicationSubmissionSubmitForm extends FormBase {

  /**
   * The mail manager.
   *
   * @var MailManager
   */
  protected $mailManager;

  /**
   * Constructs a ApplicationSubmissionSubmitForm object.
   *
   * @param MailManager $mail_manager
   */
  public function __construct(MailManager $mail_manager) {
    $this->mailManager = $mail_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.mail')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'gla_opportunity_user_submit_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ApplicationSubmission $submission = NULL) {

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => t('Submit and continue'),
      '#attributes' => [
        'class' => [
          'button--alt',
        ],
      ],
    ];

    $form['submission'] = [
      '#type' => 'value',
      '#value' => $submission,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // Validate the responses before allowing the user to continue.
    /** @var ApplicationSubmission $entity */
    $entity = $form_state->getValue('submission');
    if (!$entity) {
      // If we get here, something has gone wrong.
      $form_state->setError($form, t('There was a problem with your submission, please refresh the page and try again.'));
      return;
    }

    /** @var \Drupal\Core\Entity\EntityConstraintViolationList $violations[] */
    $violations = $entity->validate();

    // Remove violations of inaccessible fields.
    $current_user = $this->currentUser();
    $violations->filterByFieldAccess($current_user);

    // Get the entity form to lookup the title to use in the validation error message.
    $entity_form_obj = \Drupal::entityTypeManager()
      ->getFormObject($entity->getEntityTypeId(), 'default')
      ->setEntity($entity);
    $entity_form =  \Drupal::formBuilder()->getForm($entity_form_obj);

    $errors = [];
    foreach ($violations->getFieldNames() as $field_name) {
      // Set a form error for every save validation violation. As we're not
      // actually acting on the form (i.e. don't have these fields in the form)
      // we group all the errors together in one setError.
      // Find the step this question is on to get the message to display. Fallback
      // to the field name in case we can't find the step title for some reason.
      foreach ($entity_form['#steps'] as $step_number => $step_data) {
        if (in_array($field_name, $step_data->children)) {
          $label_field = $step_data->children[0];
          $label = $entity_form[$label_field]['widget']['#title'];
          $errors[$step_data->children[0]] = $label;
          break;
        }
      }
    }

    if (!empty($errors)) {
      $items = [
        '#theme' => 'item_list',
        '#list_type' => 'ul',
        '#items' => $errors,
      ];

      $form_state->setError($form, t('Answers for the following questions are required before you can proceed: <br> @items', [
        '@items' => render($items),
      ]));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Set this submission as 'submitted'.
    /** @var ApplicationSubmission $entity */
    $entity = $form_state->getValue('submission');
    $entity->setSubmitted(TRUE);
    $entity->save();

    // Get details about what this submission relates to.
    /** @var \Drupal\gla_provider\ProviderProcessor $provider_processor */
    $provider_processor = \Drupal::service('gla_provider.processor');
    $opp_node = $entity->getOpportunityNode();
    $provider_group = $provider_processor->getGroupFromEntity($opp_node);
    if (!$provider_group) {
      drupal_set_message(t('There was a problem sending your submission.'));
      return;
    }

    // Add the submission to the provider group.
    $provider_group->addContent($entity, 'group_entity_application_submission:application_submission');

    // Redirect to 'success' page.
    $form_state->setRedirect('gla_opportunity.apply_success', ['node' => $entity->getOpportunityNode()->id()]);

    // Send confirmation email to volunteer.
    $vol_email = $this->sendConfirmationEmail($entity, $opp_node);

    // Send email to providers.
    $success = FALSE;
    $members = $provider_processor->getUsersInGroup($provider_group);
    if (!empty($members)) {
      foreach ($members as $member) {
        $emails[] = $member->getEmail();
      }

      $success = $this->sendEmailToProvider($entity, $opp_node, $emails);
    }

    if (!$success) {
      drupal_set_message(t('There was a problem sending your submission.'));
    }
  }

  /**
   * Send confirmation email to volunteer.
   */
  public function sendConfirmationEmail(ApplicationSubmission $submission, Node $opp_node) {
    // Using the provider module to actually send the email (i.e. hook_mail()
    // is there) to keep them together.
    $module = 'gla_provider';
    $key = 'application_submitted_confirmation';
    $to = $submission->get('field_email')->getString();
    $langcode = $this->currentUser()->getPreferredLangcode();
    $params['title'] = t('Confirmation of Team London Application for @opp_title', ['@opp_title' => $opp_node->getTitle()]);
    $params['message'] = t("Confirmation of volunteer application at Team London. A copy of your submitted data is below.\r\n");
    $params['message'] .= $submission->generateEmailText($opp_node);
    $params['message'] .= t("\r\n
Thanks,

Team London");

    $result = $this->mailManager->mail($module, $key, $to, $langcode, $params, NULL, TRUE);
    return $result['result'];
  }

  /**
   * Send submission email to provider.
   */
  public function sendEmailToProvider(ApplicationSubmission $submission, Node $opp_node, $emails) {
    // Using the provider module to actually send the email (i.e. hook_mail()
    // is there) to keep them together.
    $module = 'gla_provider';
    $key = 'application_submitted';
    $to = implode(', ', $emails);
    $langcode = $this->currentUser()->getPreferredLangcode();
    $params['title'] = t('Team London Application for @opp_title', ['@opp_title' => $opp_node->getTitle()]);
    $params['message'] = t("New volunteer application. The submitted data is below.\r\n");
    $params['message'] .= $submission->generateEmailText($opp_node);
    $params['message'] .= t("\r\n
Thanks,
Team London");

    $result = $this->mailManager->mail($module, $key, $to, $langcode, $params, NULL, TRUE);
    return $result['result'];
  }
}
