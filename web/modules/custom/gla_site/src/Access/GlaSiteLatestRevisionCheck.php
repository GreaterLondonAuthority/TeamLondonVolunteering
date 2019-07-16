<?php

namespace Drupal\gla_site\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\Routing\Route;

class GlaSiteLatestRevisionCheck extends \Drupal\content_moderation\Access\LatestRevisionCheck {

  /**
   * {@inheritdoc}
   */
  public function access(Route $route, RouteMatchInterface $route_match, AccountInterface $account) {
    $entity = $this->loadEntity($route, $route_match);
    if ($entity->getEntityTypeId() != 'node') {
      // Use default if not a node.
      return parent::access($route, $route_match, $account);
    }

    // If this is an opportunity or provider profile and trying to view its
    // latest version, allow access if the current user is a member of the group
    // to which the content belongs.
    // This is to cover drafts etc, which the group permissions do not take into
    // account.
    $types = ['opportunity', 'provider_profile'];
    $type = $entity->bundle();
    if (!in_array($type, $types)) {
      return parent::access($route, $route_match, $account);
    }

    /** @var \Drupal\gla_provider\ProviderProcessor $provider_processor */
    $provider_processor = \Drupal::service('gla_provider.processor');
    $content_group = $provider_processor->getGroupFromEntity($entity);
    if (!$content_group) {
      return parent::access($route, $route_match, $account);
    }

    // The content belongs to a group so check the current user.
    $user_group = $provider_processor->getGroup($account);
    if (!$user_group) {
      return parent::access($route, $route_match, $account);
    }

    // If user group and content group are the same, then the user can definitely
    // access this. If they're the same we go with the default.
    if ($user_group->id() == $content_group->id()) {
      $access_result = AccessResult::allowed();
      return $access_result->addCacheableDependency($entity);
    }

    return parent::access($route, $route_match, $account);
  }
}
