<?php

namespace Drupal\gla_provider\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountInterface;
use Drupal\gla_provider\ProviderProcessor;
use Drupal\group\Entity\Group;
use Drupal\views\Views;
use Drupal\views_autocomplete_filters\Controller\ViewsAutocompleteFiltersController;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class GlaProviderViewsAutocompleteController.
 */
class GlaProviderViewsAutocompleteController extends ViewsAutocompleteFiltersController {

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * @var ProviderProcessor
   */
  protected $providerProcessor;

  /**
   * GlaProviderViewsAutocompleteController constructor.
   *
   * @param \Psr\Log\LoggerInterface $logger
   * @param \Drupal\Core\Session\AccountInterface $current_user
   */
  public function __construct(LoggerInterface $logger, AccountInterface $current_user, ProviderProcessor $provider_processor) {
    parent::__construct($logger);
    $this->currentUser = $current_user;
    $this->providerProcessor = $provider_processor;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('logger.factory')->get('views_autocomplete_filters'),
      $container->get('current_user'),
      $container->get('gla_provider.processor')
    );
  }

  /**
   * The altered _custom_access for viewsfilters.autocomplete.
   *
   * Access for the autocomplete filters path.
   *
   * Determine if the given user has access to the view. Note that
   * this sets the display handler if it hasn't been.
   *
   * @param string $view_name
   *   The View name.
   * @param string $view_display
   *   The View display.
   *
   * @return bool.
   */
  public function access($view_name, $view_display) {
    // We only need to override this check for our views with group_permission
    // access restrictions. For all others, hand back over to the contrib
    // module.
    $views_to_alter = [
      // If no displays specified then apply to all.
      'applications' => [],
      'provider_to_do' => [],
    ];

    if (isset($views_to_alter[$view_name]) && (empty($views_to_alter[$view_name]) || in_array($view_display, $views_to_alter[$view_name]))) {
      // Get the group route parameter.
      /** @var \Drupal\Core\Routing\RouteMatch $route_match */
      $route_match = \Drupal::service('current_route_match');
      $current_path = $route_match->getRouteName();
      if ($current_path == 'gla_provider.dashboard') {
        $group_id = FALSE;
        // Group is not in the URL, get from the user.
        $user = $this->currentUser;
        $group = $this->providerProcessor->getGroup($user);
        if ($group) {
          $group_id = $this->providerProcessor->getGroup($user)->id();
        }
      }
      elseif ($view_name == 'applications' && ($view_display == 'page_1' || $view_display == 'responded_applications_opportunity' || $view_display == 'pending_applications_opportunity')) {
        // There are two contextual filters.
        $view_args = $route_match->getParameter('view_args');
        $view_args_explode = explode('|', $view_args);
        $group_id = $view_args_explode[0];
      }
      elseif ($view_name == 'provider_to_do' && $view_display == 'no_group') {
        $group_id = FALSE;
      }
      else {
        $group_id = $route_match->getParameter('view_args');
      }

      if (!$group_id) {
        return parent::access($view_name, $view_display);
      }

      $group = Group::load($group_id);
      $this->group = $group;

      // Determine if the given user has access to the view. Note that
      // this sets the display handler if it hasn't been.
      $view = Views::getView($view_name);
      if ($this->viewAccess($view, $view_display)) {
        return AccessResult::allowed();
      }

      return AccessResult::forbidden();
    }

    return parent::access($view_name, $view_display);
  }


  /**
   * Adapted from \Drupal\views\ViewExecutable::access to alter the ultimate
   * access check to allow us to use the group parameter.
   *
   *
   * Determines if the given user has access to the view.
   *
   * Note that this sets the display handler if it hasn't been set.
   *
   * @param \Drupal\views\ViewExecutable $view
   * @param string $displays
   *   The machine name of the display.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user object.
   *
   * @return bool
   *   TRUE if the user has access to the view, FALSE otherwise.
   */
  public function viewAccess($view, $displays = NULL, $account = NULL) {
    // No one should have access to disabled views.
    if (!$view->storage->status()) {
      return FALSE;
    }

    if (!isset($view->current_display)) {
      $view->initDisplay();
    }

    if (!$account) {
      $account = $this->currentUser;
    }

    // We can't use choose_display() here because that function
    // calls this one.
    $displays = (array) $displays;
    foreach ($displays as $display_id) {
      if ($view->displayHandlers->has($display_id)) {
        if (($display = $view->displayHandlers->get($display_id)) && $this->groupPermissionAccessCheck($display, $account)) {
          return TRUE;
        }
      }
    }

    return FALSE;
  }

  /**
   * A combination of the two views plugin/permission access checks:
   * - \Drupal\views\Plugin\views\display\DisplayPluginBase::access
   * - \Drupal\group\Plugin\views\access\GroupPermission::access
   *
   * Uses the group we have extracted from the route params to check against.
   */
  public function groupPermissionAccessCheck($display, AccountInterface $account = NULL) {
    if (!isset($account)) {
      $account = \Drupal::currentUser();
    }

    /** @var \Drupal\group\Plugin\views\access\GroupPermission $plugin */
    $plugin = $display->getPlugin('access');
    if ($plugin && !empty($this->group)) {
      return $this->group->hasPermission($plugin->options['group_permission'], $account);
    }
    return FALSE;
  }

}
