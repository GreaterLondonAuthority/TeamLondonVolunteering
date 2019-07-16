<?php

namespace Drupal\gla_opportunity;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Drupal\content_moderation\ModerationInformation;

/**
 * Class ResponseSubscriber.
 *
 * Subscribe drupal events.
 *
 * @package Drupal\gla_opportunity
 */
class ResponseSubscriber implements EventSubscriberInterface {

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The moderation information of the current node.
   *
   * @var \Drupal\content_moderation\ModerationInformation
   */
  protected $moderationInformation;

  /**
   * Constructs a new ResponseSubscriber instance.
   *
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   * @param \Drupal\content_moderation\ModerationInformation $moderation_information
   *   The moderation information of the current node.
   */
  public function __construct(AccountInterface $current_user, ModerationInformation $moderation_information) {
    $this->currentUser = $current_user;
    $this->moderationInformation = $moderation_information;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[KernelEvents::RESPONSE][] = 'alterResponse';
    return $events;
  }

  /**
   * Redirect if 403 and node an event.
   *
   * @param FilterResponseEvent $event
   *   The route building event.
   */
  public function alterResponse(FilterResponseEvent $event) {
    if ($event->getResponse()->getStatusCode() == 403) {
      /** @var \Symfony\Component\HttpFoundation\Request $request */
      $request = $event->getRequest();
      $uri = $request->getRequestUri();
      $node = $request->attributes->get('node');
      // User is provider.
      $user_roles = $this->currentUser->getRoles();
      // Node moderation state is 'Ready for review'.

      if (!empty($node)) {
        $node_type = $node->getType();
        $moderation_state = $node->moderation_state->value;

        if (in_array('provider', $user_roles) && $moderation_state == 'ready_for_review') {
          if ($node_type == 'opportunity' || $node_type == 'provider_profile') {
            // Set message.
            drupal_set_message('Thank you for submitting this content, it is currently being reviewed', 'notice');
            // Redirect.
            $route_name = 'entity.node.canonical';
            if ($this->moderationInformation->hasPendingRevision($node)) {
              $route_name = 'entity.node.latest_version';
            }

            $redirect_url = \Drupal\Core\Url::fromRoute($route_name, ['node' => $node->id()])->toString();
            $event->setResponse(new RedirectResponse($redirect_url, 302));
          }
        }
      }
      elseif ($uri == '/user/logout') {
        $redirect_url = \Drupal\Core\Url::fromRoute('<front>')->toString();
        $event->setResponse(new RedirectResponse($redirect_url));
      }
    }
  }
}
