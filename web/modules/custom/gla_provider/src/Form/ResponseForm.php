<?php

namespace Drupal\gla_provider\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\gla_provider\ProviderProcessor;
use Drupal\user\Entity\User;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Mail\MailManager;

/**
 * ResponseForm.
 */
class ResponseForm extends FormBase {

  /**
   * @var ProviderProcessor
   */
  protected $providerProcessor;

  /**
   * @var \Drupal\group\Entity\Group
   */
  protected $providerGroup;

  /**
   * @var MailManager
   */
  protected $mailManager;

  /**
   * Constructs a ResponseForm object.
   *
   * @param ProviderProcessor $provider_processor
   */
  public function __construct(ProviderProcessor $provider_processor, MailManager $mail_manager) {
    $this->providerProcessor = $provider_processor;
    $this->mailManager = $mail_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('gla_provider.processor'),
      $container->get('plugin.manager.mail')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'gla_provider_response_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $submission = NULL) {

    $provider_user = User::load($this->currentUser()->id());
    $this->providerGroup = $this->providerProcessor->getGroupFromEntity($provider_user);

    // Get token details.
    $volunteer_name = $submission->get('field_first_name')->value . ' ' . $submission->get('field_last_name')->value;
    $provider_profile = $this->providerProcessor->getProviderProfileFromEntity($provider_user);
    $provider_org_name = $provider_profile ? $provider_profile->getTitle() : '';
    $prefix = '<div class="dashboard-response-form__options"><p class="dashboard-response-form__prefix">' . t('Dear @volunteer_name,', ['@volunteer_name' => $volunteer_name]) . '</p>';
    $suffix = '<p class="dashboard-response-form__suffix">' . t('@provider_org_name', ['@provider_org_name' => $provider_org_name]) . '</p></div>';

    $form['response_type'] = [
      '#type' => 'radios',
      '#required' => TRUE,
      '#options' => [
        'accepted' => t('Please contact us to discuss this role further'),
        'rejected' => t('We\'re sorry, you have not been selected for this role'),
      ],
      '#suffix' => t('You can edit your response to the volunteer. Your response will be saved and can be used again.'),
    ];

    $form['accepted_text'] = [
      '#type' => 'textarea',
      '#field_prefix' => $prefix,
      '#field_suffix' => $suffix,
      '#default_value' => $this->fieldValueText('accepted'),
      '#rows' => 9,
      '#states' => [
        'visible' => [
          ':input[name="response_type"]' => ['value' => 'accepted'],
        ],
      ],
    ];

    $form['rejected_text'] = [
      '#type' => 'textarea',
      '#field_prefix' => $prefix,
      '#field_suffix' => $suffix,
      '#default_value' => $this->fieldValueText('rejected'),
      '#rows' => 9,
      '#states' => [
        'visible' => [
          ':input[name="response_type"]' => ['value' => 'rejected'],
        ],
      ],
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Send response'),
      '#attributes' => [
        'class' => [
          'button',
          'button--alt',
        ],
      ],
      '#prefix' => '<div class="separator"></div>',
      '#suffix' => '<p>' . Link::createFromRoute($this->t('Cancel and go back'), 'gla_provider.dashboard')->toString() . '</p>'
    ];

    $form_state->setStorage([
      'submission' => $submission,
    ]);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    $storage = $form_state->getStorage();
    $submission = $storage['submission'];
    $response_type = $form_state->getValue('response_type');

    // Save the response in the relevant field on the group for use next time.
    switch ($response_type) {
      case 'accepted':
        $response_text = $form_state->getValue('accepted_text');
        $save_to_field_name = 'field_latest_approval_response';
        break;
      case 'rejected':
        $response_text = $form_state->getValue('rejected_text');
        $save_to_field_name = 'field_latest_rejection_response';
        break;
      default:
        $save_to_field_name = FALSE;
    }

    if ($save_to_field_name && $this->providerGroup->hasField($save_to_field_name) && !empty($response_text)) {
      $this->providerGroup->set($save_to_field_name, $response_text);
      $this->providerGroup->save();
    }

    // Get the prefix and suffix used to save also.
    $prefix = strip_tags($form['accepted_text']['#field_prefix']);
    $suffix = strip_tags($form['accepted_text']['#field_suffix']);
    $response_text = "$prefix\r\n\r\n$response_text\r\n\r\n$suffix";

    // Register that this application has been responded to and save the actual response.
    $submission->set('responded', TRUE);
    $submission->set('field_application_status', $response_type);
    $submission->set('field_response', $response_text);
    $submission->save();

    // Get volunteer email.
    $volunteer_email = $submission->get('field_email')->value;

    // Send email.
    $this->responseMessage($response_text, $response_type, $volunteer_email);

    // Go to success page.
    $form_state->setRedirect('gla_provider.application_respond_success', ['application_submission' => $submission->id()]);
  }

  /**
   * Returns default response text.
   *
   * @return string
   */
  protected function defaultText($response_type) {
    $text = [
      'accepted' => t('Thank you for your interest in this volunteering role.

We are keen for you to volunteer with us and would like to discuss the details of this role further with you. 

We will be in touch shortly.

Kind wishes,'),
      'rejected' => t('Thank you for your interest in this volunteering role.

Unfortunately we\'re no longer accepting volunteers for this role as it has now been filled. We\'re sorry if this is disappointing. 

We wish you all the best in your future volunteering. 

Kind wishes,'),
    ];

    return $text[$response_type];
  }

  /**
   * Returns the provider's latest response of this type.
   *
   * @return string|boolean
   */
  protected function latestResponseText($response_type) {

    switch ($response_type) {
      case 'accepted':
        $field_name = 'field_latest_approval_response';
        break;
      case 'rejected':
        $field_name = 'field_latest_rejection_response';
        break;
      default:
        return FALSE;
    }

    // Load data from group entity.
    if ($this->providerGroup->hasField($field_name) && !$this->providerGroup->get($field_name)->isEmpty()) {
      return $this->providerGroup->get($field_name)->value;
    }

    return FALSE;
  }

  /**
   * Determines the text to display in the respond text field.
   *
   * @return string
   */
  protected function fieldValueText($response_type) {
    // First check for a previous value submitted by the provider.
    $value = $this->latestResponseText($response_type);
    if (!$value) {
      // If none, default to the GLA-supplied text.
      $value = $this->defaultText($response_type);
    }

    return $value;
  }

  /**
   * Sends mail message.
   */
  protected function responseMessage($response_text, $key, $email) {
    $module = 'gla_provider';
    $langcode = \Drupal::currentUser()->getPreferredLangcode();
    $send = TRUE;

    // Send approval message.
    if ($key == 'accepted') {
      $params['message'] = $response_text;
      $params['title'] = t('Application approved');
    }
    // Send rejection message.
    elseif ($key == 'rejected') {
      $params['message'] = $response_text;
      $params['title'] = t('Application declined');
    }

    $messenger = \Drupal::messenger();
    $result = $this->mailManager->mail($module, $key, $email, $langcode, $params, NULL, $send);
    if ($result['result'] != TRUE) {
      $message = t('There was a problem sending your email notification to @email.', ['@email' => $email]);
      $messenger->addMessage($message, $messenger::TYPE_ERROR);
      return;
    }

    $message = t('An email notification has been sent to @email ', ['@email' => $email]);
    $messenger->addMessage($message);
  }
}
