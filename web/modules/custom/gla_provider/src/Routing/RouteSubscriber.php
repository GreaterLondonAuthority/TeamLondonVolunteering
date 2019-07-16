<?php

namespace Drupal\gla_provider\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Listens to the dynamic route events.
 */
class RouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    // Override viewsfilters.autocomplete route.
    if ($route = $collection->get('viewsfilters.autocomplete')) {
      // Override the access check to work with group permissions.
      $route->setRequirement('_custom_access', '\Drupal\gla_provider\Controller\GlaProviderViewsAutocompleteController::access');
    }
  }

}
