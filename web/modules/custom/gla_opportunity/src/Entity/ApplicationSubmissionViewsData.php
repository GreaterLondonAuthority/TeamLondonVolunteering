<?php

namespace Drupal\gla_opportunity\Entity;

use Drupal\views\EntityViewsData;

/**
 * Provides Views data for Application submission entities.
 */
class ApplicationSubmissionViewsData extends EntityViewsData {

  /**
   * {@inheritdoc}
   */
  public function getViewsData() {
    $data = parent::getViewsData();

    // Additional information for Views integration, such as table joins, can be
    // put here.

    return $data;
  }

}
