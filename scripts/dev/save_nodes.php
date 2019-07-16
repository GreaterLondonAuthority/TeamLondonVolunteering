<?php

// Save the nodes again so that the status field can be updated.
$opportunity_nodes = \Drupal::entityTypeManager()->getStorage('node')->loadByProperties([
  'type' => 'opportunity'
]);
foreach ($opportunity_nodes as $opportunity_node) {
  $opportunity_node->save();
}