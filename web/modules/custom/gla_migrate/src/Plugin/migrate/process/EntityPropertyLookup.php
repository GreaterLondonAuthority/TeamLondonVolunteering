<?php

namespace Drupal\gla_migrate\Plugin\migrate\process;


use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;

/**
 * Get an entity ID by the specified property.
 *
 * @MigrateProcessPlugin(
 *   id = "entity_property_lookup",
 * )
 */
class EntityPropertyLookup extends ProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    $entity_type = $this->configuration['entity_type'] ?: 'node';
    $entity_bundle = $this->configuration['entity_bundle'];
    $entity_property = $this->configuration['entity_property'];

    // Load the entity by properties.
    // todo: think of a way to set more properties? tried once but couldn't find a way
    // to set multiple 'sources'.
    $provider_profiles = \Drupal::entityTypeManager()->getStorage($entity_type)->loadByProperties([
      'type' => $entity_bundle,
      $entity_property => $value,
    ]);
    if ($provider_profiles) {
      $provider_profile = reset($provider_profiles);
      return $provider_profile->id();
    }
    else {
      return FALSE;
    }

  }
}