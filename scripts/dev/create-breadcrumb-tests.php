<?php

use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\Core\Language\LanguageInterface;
use Drupal\taxonomy\Entity\Term;

// For quick setup of vocabs and terms needed for the very specific breadcrumb functionality.

// Check if the 'body_of_work' vocabulary already exists.
$vocabulary = Vocabulary::load('body_of_work');
if (!$vocabulary) {
  // Create vocab.
  $vocabulary = Vocabulary::create([
    'name' => 'Body of work',
    'description' => 'Body of work test vocab',
    'vid' => 'body_of_work',
    'langcode' => LanguageInterface::LANGCODE_NOT_SPECIFIED,
  ]);

  $vocabulary->save();
}

$vid = $vocabulary->id();

// Add two terms and add associations.
$term_1 = Term::create([
  'name' => 'Term 1',
  'vid' => $vid,
  'langcode' => LanguageInterface::LANGCODE_NOT_SPECIFIED,
]);
$term_1->save();

$term_2 = Term::create([
  'name' => 'Term 2',
  'vid' => $vid,
  'langcode' => LanguageInterface::LANGCODE_NOT_SPECIFIED,
  'parent' => $term_1->id(),
]);
$term_2->save();

$url = $term_2->url();
echo "\r\nURL: $url\r\n";
