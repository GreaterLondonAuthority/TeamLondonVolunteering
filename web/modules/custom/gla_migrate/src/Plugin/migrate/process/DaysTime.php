<?php

namespace Drupal\gla_migrate\Plugin\migrate\process;

use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;
use Drupal\node\Entity\Node;


/**
 *
 * @MigrateProcessPlugin(
 *   id = "days_time",
 * )
 */
class DaysTime extends ProcessPluginBase {

  const DAYS_MAPPING = [
    'mon' => 'monday',
    'tues' => 'tuesday',
    'wed' => 'wednesday',
    'thur' => 'thursday',
    'fri' => 'friday',
    'sat' => 'saturday',
    'sun' => 'sunday',
  ];

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    // Do a trim of normal white spaces.
    $value = trim($value);

    // Get the identifier from migration row.
    $nid = $row->getDestinationProperty('nid');

    $days_mapping = self::DAYS_MAPPING;

    $existing = [];
    // Get the existing entity, if it exists.
    if ($node = Node::load($nid)) {
      $existing = $node->{$destination_property}->getValue();
      $existing = array_column($existing, 'value');
    }

    // If we are dealing with the row with data for the current field we are processing
    // then append the time of day value to the current field's values.
    if ($destination_property == 'field_' . $days_mapping[$value]) {
      $new_time_of_day = $row->getSourceProperty('day_fragment_guid');
      // Append row's time of day value to current value
      $merge = array_merge($existing, [$new_time_of_day]);
      $existing = array_unique($merge);
    }

    return $existing;
  }
}
