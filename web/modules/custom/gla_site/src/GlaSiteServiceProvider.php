<?php

namespace Drupal\gla_site;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;

/**
 * Modifies the access_check.latest_revision service.
 */
class GlaSiteServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    // Overrides access_check.latest_revision class to use own custom implementation.
    $definition = $container->getDefinition('access_check.latest_revision');
    $definition->setClass('Drupal\gla_site\Access\GlaSiteLatestRevisionCheck');
  }
}
