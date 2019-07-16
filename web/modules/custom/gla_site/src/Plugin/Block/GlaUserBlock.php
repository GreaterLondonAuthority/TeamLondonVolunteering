<?php

namespace Drupal\gla_site\Plugin\Block;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Link;
use Drupal\Core\Session\AccountInterface;

/**
 * Provides the GLA user block.
 *
 * @Block(
 *   id = "gla_site_user_block",
 *   admin_label = @Translation("GLA User Block"),
 * )
 */
class GlaUserBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  protected function blockAccess(AccountInterface $account) {
    return AccessResult::allowed();
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    // Get profile link depending on the current user.
    $items = $this->getUserLinks();
    return [
      '#theme' => 'item_list',
      '#list_type' => 'ul',
      '#items' => $items,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    return Cache::mergeContexts(parent::getCacheContexts(), ['user']);
  }

  /**
   * Get links depending on the current user.
   */
  protected function getUserLinks() {

    $links = [];
    if (\Drupal::currentUser()->isAnonymous()) {
      // Just show the login link.
      $links[] = Link::createFromRoute(t('Login'), 'user.login');
      return $links;
    }

    // Add logout link first.
    $links[] = Link::createFromRoute(t('Logout'), 'user.logout');

    // Different dashboard link depending on user.
    $user_roles = \Drupal::currentUser()->getRoles();
    if (in_array('provider', $user_roles)) {
      // Provider dashboard link.
      $links[] = Link::createFromRoute(t('Dashboard'), 'gla_provider.dashboard');
    }
    elseif (in_array('volunteer', $user_roles)) {
      // Volunteer dashboard link.
      $links[] = Link::createFromRoute(t('Dashboard'), 'gla_volunteer.dashboard');
    }

    return $links;
  }
}
