<?php

namespace Drupal\gla_migrate\Plugin\migrate\process;

use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\MigrateSkipProcessException;
use Drupal\migrate\Row;
use Drupal\taxonomy\Entity\Term;


/**
 * Fetches the taxonomy term by referencing the taxonomy id from the taxonomy name.
 * Can be used for all the taxonomy term references.
 *
 * @MigrateProcessPlugin(
 *   id = "taxonomy_by_name",
 * )
 */
class TaxonomyByName extends ProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    // Do a trim of normal white spaces.
    $value = trim($value);

    $create = $this->configuration['create'];
    $vocabulary = $this->configuration['vocabulary'];
    $include_parents = $this->configuration['include_parents'];

    $tids = $this->getTidByName($value, $vocabulary, $include_parents);

    // If we haven't found a term AND we are allowing auto creation of terms.
    // the tick box to 'create' terms on the fly needs to be ticked on the field itself.
    if (is_null($tids) && $create) {
      $vocabulary = $this->configuration['vocabulary'];
      // Quickly check if vocabulary was passed, otherwise we won't know where to create the terms!
      if (!isset($vocabulary)) return NULL;
      return $this->createTerm($value, $vocabulary);
    }
    else {
      return $tids;
    }
  }

  protected function getTidByName($name, $vocabulary = NULL, $include_parents = FALSE) {
    $properties = [];
    if (!empty($name)) {
      $properties['name'] = $name;
      if (isset($vocabulary)) {
        $properties['vid'] = $vocabulary;
      }
    }
    else {
      // Used to call throw new MigrateSkipProcessException(); but if they leave an extra 'comma'
      // then it doesn't break the rest of the terms.
      return NULL;
    }

    $terms = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties($properties);
    $term = reset($terms);

    if (!empty($term)) {
      if ($include_parents) {
        $parents = $term->parent;

        if (!empty($parents)) {
          // @todo - atm only getting the first parent. Can it have more than one?
          // For our purposes on this migration we'll only have one level of parent so it's ok.
          return [$term->id(), $term->parent[0]->target_id];
        }
        else {
          return $term->id();
        }
      }
      else {
        return $term->id();
      }
    }
    else {
      return NULL;
    }
  }

  /**
   * Create new terms on the fly for given taxonomy.
   */
  protected function createTerm($name, $vocabulary) {
    $term = Term::create([
      'name' => $name,
      'vid' => $vocabulary,
    ]);
    $term->save();
    return $term->id();
  }

}
