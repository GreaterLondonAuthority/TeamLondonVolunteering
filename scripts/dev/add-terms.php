<?php

use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;

include_once 'terms_to_add.php';

/**
 * Script to add all terms at to Vocab with hierarchy.
 */
if (isset($terms_to_be_added) && is_array($terms_to_be_added)) {

  foreach ($terms_to_be_added as $vid => $base_terms) {
    foreach ($base_terms as $terms_list) {
      if (is_array($terms_list)) {
        addTerms($terms_list, $base_terms['vid']);
      }
    }
  }
}
else {
  echo 'Terms array not in the correct form.' . PHP_EOL;
}

/**
 * Add the terms through recursion.
 *
 * @param $terms
 * @param $vid
 * @param int $parent_id
 */
function addTerms($terms, $vid, $parent_id = 0) {
  if (isset($terms['children'])) {
    $parent_id = createTermAndReturnId($terms['title'], $vid, $parent_id);
    foreach ($terms['children'] as $key => $child) {
      addTerms($child, $vid, $parent_id);
    }
  }
  else {
    $parent_id = createTermAndReturnId($terms['title'], $vid, $parent_id);
    return;
  }
}

/**
 * Creates the terms and returns the parent id to reference for the children.
 *
 * @param $title
 * @param $vid
 * @param int $parent_id
 * @return int|mixed|null|string
 */
function createTermAndReturnId($title, $vid, $parent_id = 0) {
  if ($parent_id !== 0) {
    $term = Term::create([
      'name' => $title,
      'vid' => $vid,
      'parent' => $parent_id
    ]);
  }
  else {
    $term = Term::create([
      'name' => $title,
      'vid' => $vid,
    ]);
  }
  $term->save();
  return $term->id();
}
