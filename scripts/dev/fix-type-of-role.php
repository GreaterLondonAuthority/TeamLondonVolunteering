<?php

// Command from /web:  drush scr ../scripts/dev/fix-type-of-role.php

// Update the type of roles values to the correct ones.

/** @var \Drupal\Core\Database\Connection $connection */
$connection = Drupal::service('database');

$map = [
  'orporate _groups' => 'corporate_groups',
  'corporate _groups' => 'corporate_groups',
  '16 -17' => '16_17',
  'corporate_group' => 'corporate_groups',
  'individuals_' => 'individuals',
  'trustees' => 'trustee',
  '_16 -17' => '16_17',
  '_16_17' => '16_17',
  '_corporate_group' => 'corporate_groups',
];

foreach ($map as $wrong => $right) {
  // NOTE: We use LIKE and _ so the spaces aren't ignored.

  // Query and update main table.
  $num_updated_node = $connection->update('node__field_type_options_type')
    ->fields([
      'field_type_options_type_value' => $right,
    ])
    ->condition('field_type_options_type_value', $wrong, 'LIKE')
    ->execute();

  // Query and update revisions table.
  $num_updated_node_revision = $connection->update('node_revision__field_type_options_type')
    ->fields([
      'field_type_options_type_value' => $right,
    ])
    ->condition('field_type_options_type_value', $wrong, 'LIKE')
    ->execute();

  echo "\r\nUpdated $num_updated_node node table entries and $num_updated_node_revision node revision table entries from $wrong to $right.\r\n";
}

echo "\r\nFlushing all caches.\r\n";
drupal_flush_all_caches();
echo "\r\nComplete.\r\n";
