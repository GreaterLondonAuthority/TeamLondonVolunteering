<?php

namespace Drupal\gla_migrate\Plugin\migrate\source;

use Drupal\group\Entity\Group;
use Drupal\migrate\MigrateSkipRowException;
use Drupal\migrate_source_csv\Plugin\migrate\source\CSV;
use Drupal\migrate\Row;
use Drupal\migrate\Event\MigrateEvents;
use Drupal\migrate\Event\MigratePostRowSaveEvent;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\Entity\EntityManagerInterface;

/**
 * Source plugin for GLA Provider Groups.
 *
 * @MigrateSource(
 *   id = "gla_provider_groups"
 * )
 */
class GlaProviderGroups extends CSV {

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, MigrationInterface $migration) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $migration);
    \Drupal::service('event_dispatcher')->addListener(MigrateEvents::POST_ROW_SAVE, array($this, 'onPostRowSave'));
  }


  /**
   *
   * {@inheritdoc}
   */
  public function onPostRowSave(MigratePostRowSaveEvent $event) {
    $migration = $event->getMigration()->id();
    if ($migration != 'gla_provider_groups') return;

    // Row object containing the specific item just imported.
    $row = $event->getRow();

    $destination_id_values = $event->getDestinationIdValues();
    $destination_id = $destination_id_values[0];

    $destination_uid = $row->getDestinationProperty('uid');
    $destination_provider_profile_id = $row->getDestinationProperty('field_provider_profile');

    if (!isset($destination_uid)) {
      throw new MigrateSkipRowException('Missing destination UID');
    }
    if (!isset($destination_provider_profile_id)) {
      throw new MigrateSkipRowException('Missing destination Provider Profile ID');
    }

    // Load the profile provider so we can add it to the group.
    $storage = \Drupal::entityTypeManager()->getStorage('node');
    $provider_profile = $storage->load($destination_provider_profile_id);

    // Load all opportunities belonging to the provider.
    $storage = \Drupal::entityTypeManager()->getStorage('node');
    $existing_opportunities = $storage->loadByProperties([
      'type' => 'opportunity',
      'uid' => $destination_uid,
    ]);

    // Load the group that just got migrated.
    $provider_group = Group::load($destination_id);

    // Make sure it actually got created ok.
    if ($provider_group !== NULL) {
      // Add the provider profile as content of the group

      if (isset($provider_profile)) {
        $provider_group->addContent($provider_profile, 'group_node:provider_profile');

        // Set the profile provider group that it belongs to.
        $provider_profile->set('field_provider_group', $provider_group->id());
        $provider_profile->setNewRevision(FALSE);
        $provider_profile->save();

        // Add the author of the provider profile node to the group.
        $author = $provider_profile->getOwner();
        $provider_group->addMember($author);
        $provider_group->save();
      }

      // Add each provider opportunity as group content.
      foreach ($existing_opportunities as $opportunity) {
        $provider_group->addContent($opportunity, 'group_node:opportunity');
      }
    }
  }
}