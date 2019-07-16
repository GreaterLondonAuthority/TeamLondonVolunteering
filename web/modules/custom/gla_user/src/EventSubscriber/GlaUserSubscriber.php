<?php

namespace Drupal\gla_user\EventSubscriber;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Config\Entity\ThirdPartySettingsInterface;
use Drupal\Core\Entity\EntityFormBuilder;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Routing\CurrentRouteMatch;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\gla_provider\ProviderProcessor;
use Drupal\node\Entity\Node;
use Drupal\user\Entity\User;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class GlaUserSubscriber implements EventSubscriberInterface {

  /**
   * @var AccountInterface
   */
  protected $currentUser;

  /**
   * @var MessengerInterface
   */
  protected $messenger;

  /**
   * @var CurrentRouteMatch
   */
  protected $routeMatch;

  /**
   * @var ConfigFactory
   */
  protected $config;

  /**
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $request;

  /**
   * @var EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * @var EntityFormBuilder
   */
  protected $entityFormBuilder;

  /**
   * @var CacheBackendInterface
   */
  protected $cache;

  /**
   * @var ProviderProcessor
   */
  protected $providerProcessor;

  /**
   * Constructs a new EventSubscriber instance.
   *
   * @param AccountInterface $current_user
   * @param MessengerInterface $messenger
   * @param CurrentRouteMatch $route_match
   * @param ConfigFactory $config_factory
   * @param RequestStack $request_stack
   */
  public function __construct(AccountInterface $current_user, MessengerInterface $messenger, CurrentRouteMatch $route_match, ConfigFactory $config_factory, RequestStack $request_stack, EntityTypeManagerInterface $entity_type_manager, EntityFormBuilder $entity_form_builder, CacheBackendInterface $cache, ProviderProcessor $provider_processor) {
    $this->currentUser = $current_user;
    $this->messenger = $messenger;
    $this->routeMatch = $route_match;
    $this->config = $config_factory;
    $this->request = $request_stack->getCurrentRequest();
    $this->entityTypeManager = $entity_type_manager;
    $this->entityFormBuilder = $entity_form_builder;
    $this->cache = $cache;
    $this->providerProcessor = $provider_processor;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[KernelEvents::REQUEST][] = ['volunteersMustCompleteData'];
    $events[KernelEvents::REQUEST][] = ['providersMustCompleteData'];
    return $events;
  }

  /**
   * Redirect if the user has not completed all their equal opportunities data.
   */
  public function volunteersMustCompleteData(GetResponseEvent $event) {
    // Only act on volunteers.
    if ($this->currentUser->isAnonymous() || gla_site_is_admin() || !in_array('volunteer', $this->currentUser->getRoles())) {
      return;
    }

    $is_skip_route = $this->isSkipRedirectRoute();
    if ($is_skip_route) {
      return;
    }

    // For volunteers, we want to keep them in the equal opportunities journey
    // if they have not completed all questions.
    $user = User::load($this->currentUser->id());
    $complete_status = $this->equalOppsComplete($user);
    if (!$complete_status) {
      // Some required fields haven't been completed. Redirect back to equal
      // opportunities with a message.
      $config = $this->config->get('gla_site.registration_flow_settings');
      $equal_opp_nid = $config->get('node:equal_opportunities_monitoring');
      $this->messenger->addMessage(t('You must complete these questions before continuing.'), 'warning');
      $redirect_url = Url::fromRoute('entity.node.canonical', ['node' => $equal_opp_nid])->toString();
      $event->setResponse(new RedirectResponse($redirect_url));
    }
  }

  /**
   * Return if this is an equal opportunities route, or other route on which to
   * skip redirect.
   *
   * @return bool
   */
  public function isSkipRedirectRoute() {

    $volunteer_valid_routes = [
      'gla_volunteer.start',
      'gla_volunteer.equal_opportunities',
      'gla_volunteer.equal_opportunities_check',
    ];

    // Check if this is an equal opportunities route.
    $route_name = $this->routeMatch->getRouteName();
    $pass_reset = $this->request->query->get('pass-reset-token');
    if ($route_name == 'entity.node.canonical') {
      // Check if it's the equal opportunities content page.
      $config = $this->config->get('gla_site.registration_flow_settings');
      $equal_opp_nid = $config->get('node:equal_opportunities_monitoring');
      /** @var \Drupal\node\Entity\Node $route_node */
      $route_node = $this->routeMatch->getParameter('node');
      if ($route_node && $route_node->id() == $equal_opp_nid) {
        return TRUE;
      }
    }
    elseif (in_array($route_name, $volunteer_valid_routes)) {
      return TRUE;
    }
    // Other routes to skip.
    elseif (strpos($route_name, 'system') !== FALSE) {
      return TRUE;
    }
    elseif ($route_name == 'user.logout') {
      return TRUE;
    }
    elseif ($pass_reset) {
      return TRUE;
    }
    elseif ($this->request->getMethod() != 'GET') {
      return TRUE;
    }

    return FALSE;
  }

  /**
   * Check if there are any incomplete required equal opps fields for this user.
   * @param \Drupal\user\Entity\User $user
   *
   * @return bool
   */
  public function equalOppsComplete(User $user) {

    // Check if we have this data cached.
    $cid = "user_equal_opps_complete:{$user->id()}";
    $cached_data = $this->cache->get($cid);
    if ($cached_data !== FALSE) {
      return $cached_data->data;
    }

    // Don't include these fields in the count.
    $skip_fields = [
      'field_last_password_reset',
      'field_password_expiration',
      'field_first_name',
      'field_last_name',
    ];

    // Equal opportunities form.
    $equal_opp_complete = 1;
    $user_fields = $user->getFields();
    $user_form = $this->entityFormBuilder->getForm($user, 'equal_opportunities');
    foreach ($user_form as $field_name => $form_field) {
      if (!isset($user_fields[$field_name]) || in_array($field_name, $skip_fields)) {
        continue;
      }

      $field = $user_fields[$field_name];
      $field_definition = $field->getFieldDefinition();
      if ($field_definition instanceof ThirdPartySettingsInterface) {
        if ($field_definition->getType() == 'multistep_separator') {
          // Skip these.
          continue;
        }

        // Get the multiple_registration settings for this field - role type.
        if ($field_definition->getThirdPartySetting('multiple_registration', 'user_additional_register_form')) {
          $field_roles = $field_definition->getThirdPartySetting('multiple_registration', 'user_additional_register_form');
          if (!isset($field_roles['volunteer']) || !$field_roles['volunteer']) {
            // Only interested in volunteer fields.
            continue;
          }
        }

        // Check the normal 'required' property first.
        $required = FALSE;
        if (!$field_definition->isRequired()) {
          // If the field is not marked as required overall, we need to check
          // the multiple_registration settings.
          if ($field_definition->getThirdPartySetting('multiple_registration', 'user_additional_register_form_required')) {
            $field_roles_required = $field_definition->getThirdPartySetting('multiple_registration', 'user_additional_register_form_required');
            if (isset($field_roles_required['volunteer']) && $field_roles_required['volunteer']) {
              // The field is required for volunteers.
              $required = TRUE;
            }
          }
        }
        else {
          $required = TRUE;
        }

        // Check if we have a value for this field.
        if ($required && ($user->get($field_name)->isEmpty() || ($field_name == 'field_tandc' && !$user->get($field_name)->value))) {
          // This field is incomplete, no need to check any others.
          $equal_opp_complete = 0;
          break;
        }
      }
    }

    // Cache this until user is updated.
    $tags = ["user:{$user->id()}"];
    $this->cache->set($cid, $equal_opp_complete, -1, $tags);

    return $equal_opp_complete;
  }

  /**
   * Add message if the user has not completed their profile data.
   */
  public function providersMustCompleteData(GetResponseEvent $event) {
    // Only act on providers.
    if ($this->currentUser->isAnonymous() || gla_site_is_admin() || !in_array('provider', $this->currentUser->getRoles())) {
      return;
    }

    // Only act on migrated providers.
    if (!$this->isMigratedUser()) {
      return;
    }

    $is_skip_route = $this->isSkipMessageRoute();
    if ($is_skip_route) {
      return;
    }

    // For providers, we want to remind them to complete their profile if not
    // yet done.
    $profile = $this->providerProcessor->getUserProviderProfile($this->currentUser, TRUE);
    if ($profile) {
      $complete_status = $this->profileComplete($profile);
      if (!$complete_status) {
        $profile_nid = $profile->id();
        $complete_link = Link::createFromRoute(t('provider profile'), 'gla_provider.application_overview', ['node' => $profile_nid])->toString();
        $this->messenger->addMessage(t('Please complete and submit your @provider_profile before proceeding.', [
          '@provider_profile' => $complete_link,
        ]), 'warning');
      }
    }
  }

  /**
   * Check if the user has been migrated.
   */
  public function isMigratedUser() {
    $uid = $this->currentUser->id();
    $query = \Drupal::database()->select('migrate_map_gla_providers', 'map')
      ->fields('map', ['sourceid1'])
      ->condition('map.destid1', $uid)
      ->execute();

    $res = $query->fetch();
    if (!empty($res)) {
      return TRUE;
    }

    return FALSE;
  }

  /**
   * Return if this is the edit route, or other route on which to skip message.
   *
   * @return bool
   */
  public function isSkipMessageRoute() {

    $provider_valid_routes = [
      'gla_provider.application_overview',
      'gla_provider.application_check',
    ];

    $route_name = $this->routeMatch->getRouteName();
    $pass_reset = $this->request->query->get('pass-reset-token');
    if ($route_name == 'entity.node.edit_form') {
      /** @var \Drupal\node\Entity\Node $route_node */
      $route_node = $this->routeMatch->getParameter('node');
      if ($route_node && $route_node->bundle() == 'provider_profile') {
        return TRUE;
      }
    }
    elseif (in_array($route_name, $provider_valid_routes)) {
      return TRUE;
    }
    // Other routes to skip.
    elseif (strpos($route_name, 'system') !== FALSE) {
      return TRUE;
    }
    elseif ($route_name == 'user.logout') {
      return TRUE;
    }
    elseif ($pass_reset) {
      return TRUE;
    }
    elseif ($this->request->getMethod() != 'GET') {
      return TRUE;
    }
    elseif ($route_name == 'entity_browser.gla_provider_image_browser') {
      return TRUE;
    }

    return FALSE;
  }

  /**
   * Check if there are any incomplete required fields for this node.
   * @param \Drupal\node\Entity\Node $node
   *
   * @return bool
   */
  public function profileComplete(Node $node) {

    // Check if we have this data cached.
    $cid = "profile_is_complete:{$node->id()}";
    $cached_data = $this->cache->get($cid);
    if ($cached_data !== FALSE) {
      return $cached_data->data;
    }

    // Check if latest revision has been submitted.
    $profile_complete = 1;
    $latest_rev_node = $this->providerProcessor->loadLatestRevision($node);
    $latest_mod_state = $latest_rev_node->get('moderation_state')->value;
    if ($latest_mod_state != 'ready_for_review') {

      $node_fields = $node->getFields();
      $node_form = $this->entityFormBuilder->getForm($node);
      foreach ($node_form as $field_name => $form_field) {
        if (!isset($node_fields[$field_name])) {
          continue;
        }

        $field = $node_fields[$field_name];
        $field_definition = $field->getFieldDefinition();
        if ($field_definition instanceof ThirdPartySettingsInterface) {
          if ($field_definition->getType() == 'multistep_separator') {
            // Skip these.
            continue;
          }

          if ($field_definition->isRequired()) {
            // Check if we have a value for this field.
            if ($node->get($field_name)->isEmpty()) {
              // This field is incomplete, no need to check any others.
              $profile_complete = 0;
              break;
            }
          }
        }
      }
    }

    // Cache this until node is updated.
    $tags = ["node:{$node->id()}"];
    $this->cache->set($cid, $profile_complete, -1, $tags);

    return $profile_complete;
  }

}
