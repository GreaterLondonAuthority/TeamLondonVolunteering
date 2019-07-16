<?php

namespace Drupal\gla_provider\Controller;

use Drupal\Core\Database\Connection;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityFormBuilder;
use Drupal\Core\Link;
use Drupal\Core\Session\AccountProxy;
use Drupal\gla_opportunity\Controller\GlaOpportunityApplicationController;
use Drupal\gla_opportunity\Entity\ApplicationSubmission;
use Drupal\gla_provider\ProviderProcessor;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\gla_opportunity\Controller\GlaOpportunityController;
use Drupal\Core\Form\FormBuilderInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\Core\Url;

/**
 * Class GlaProviderApplicationsController.
 */
class GlaProviderApplicationsController extends GlaOpportunityApplicationController {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The database connection object.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * The form builder.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected $formBuilder;

  /**
   * @var DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * @var ProviderProcessor
   */
  protected $providerProcessor;

  /**
   * Constructs a GlaProviderApplicationsController object.
   *
   * @param EntityFormBuilder $entity_form_builder
   * @param ProviderProcessor $provider_processor
   * @param \Drupal\Core\Form\FormBuilderInterface $form_builder
   * @param DateFormatterInterface $date_formatter
   */
  public function __construct(ProviderProcessor $provider_processor, EntityFormBuilder $entity_form_builder, EntityTypeManagerInterface $entity_type_manager, Connection $connection, FormBuilderInterface $form_builder, AccountProxy $current_user, DateFormatterInterface $date_formatter) {
    parent::__construct($entity_form_builder, $entity_type_manager, $current_user);
    $this->entityTypeManager = $entity_type_manager;
    $this->connection = $connection;
    $this->formBuilder = $form_builder;
    $this->dateFormatter = $date_formatter;
    $this->providerProcessor = $provider_processor;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('gla_provider.processor'),
      $container->get('entity.form_builder'),
      $container->get('entity_type.manager'),
      $container->get('database'),
      $container->get('form_builder'),
      $container->get('current_user'),
      $container->get('date.formatter')
    );
  }

  /**
   * The _title_callback for gla_provider.application_respond.
   */
  public function respondTitle(ApplicationSubmission $application_submission) {
    // If this application has already been responded to then show the details.
    if ($application_submission->get('responded')->value) {
      return $this->t('Response to volunteer');
    }

    // Otherwise it'll be the respond page.
    return $this->t('Respond to volunteers');
  }

  /**
   * Controller for gla_provider.application_respond.
   *
   * @return array
   */
  public function respond(ApplicationSubmission $application_submission) {
    $opportunity = $application_submission->getOpportunityNode();

    // If this application has already been responded to then show the details.
    if ($application_submission->get('responded')->value) {
      $response = $application_submission->get('field_response')->getString();
      $volunteer_name = $application_submission->get('field_first_name')->value . ' ' . $application_submission->get('field_last_name')->value;
      $volunteer_email = $application_submission->get('field_email')->value;

      $date_submitted = FALSE;
      $date = $application_submission->get('submitted_timestamp')->value;
      if ($date) {
        $date_submitted = $this->dateFormatter->format($date, 'custom', 'd/m/Y');
      }

      $submission_details = [
        'date_submitted' => $date_submitted,
        'volunteer_name' => $volunteer_name,
        'volunteer_email' => $volunteer_email,
        'role_title' => $opportunity->link(),
        'status' => t('Responded'),
      ];

      return [
        '#theme' => 'gla_provider__response_submitted',
        '#back' => Link::createFromRoute($this->t('Back'), 'gla_provider.dashboard', [], ['attributes' => ['class' => 'link link--chevron-back']]),
        '#submission_details' => $submission_details,
        '#response' => $response,
      ];
    }

    // Start date.
    $start_date = '';
    $when_needed = $opportunity->get('field_dates_needed')->value;
    switch ($when_needed) {
      case 'one_off':
        if ($opportunity->get('field_one_off_date')->date) {
          $start_date = $opportunity->get('field_one_off_date')->date->format('d/m/Y');
        }
        break;
      case 'ongoing':
      default:
        if ($opportunity->get('field_ongoing_start_date')->date) {
          $start_date = $opportunity->get('field_ongoing_start_date')->date->format('d/m/Y') . ' (' . t('ongoing') . ')';
        }
        break;
    }

    $role_details = [
      'title' => $opportunity->getTitle(),
      'start_date' => $start_date,
    ];

    $submission_details = $this->applicationDataExtract($opportunity, $application_submission);
    $response_form = $this->formBuilder->getForm('\Drupal\gla_provider\Form\ResponseForm', $application_submission);

    return [
      '#theme' => 'gla_provider__respond',
      '#back' => Link::createFromRoute($this->t('Back'), 'gla_provider.dashboard', [], ['attributes' => ['class' => 'link link--chevron-back']]),
      '#role_details' => $role_details,
      '#submission_details' => $submission_details,
      '#response_form' => $response_form,
    ];
  }

  /**
   * Controller for gla_provider.application_respond_success.
   *
   * @return array
   */
  public function respondSuccess(ApplicationSubmission $application_submission) {
    $group = $this->providerProcessor->getGroup($this->user);
    $opportunity = $application_submission->getOpportunityNode();

    $volunteers_responded_link = Link::createFromRoute($this->t('See the full list of volunteers you have responded to'), 'view.applications.all_applications',
      ['group' => $group->id()],
      ['query' => ['responded' => 1]]
    );

    $all_interest_in_role_link = Link::createFromRoute($this->t('Return to see all interest in this role'), 'view.applications.page_1',
      ['group' => $group->id(), 'node' => $opportunity->id()]
    );

    return [
      '#theme' => 'gla_provider__respond_success',
      '#volunteers_responded_link' => $volunteers_responded_link,
      '#all_interest_in_role_link' => $all_interest_in_role_link,
    ];
  }

}
