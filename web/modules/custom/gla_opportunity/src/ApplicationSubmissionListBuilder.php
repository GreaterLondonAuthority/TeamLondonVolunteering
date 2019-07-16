<?php

namespace Drupal\gla_opportunity;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Link;

/**
 * Defines a class to build a listing of Application submission entities.
 *
 * @ingroup gla_opportunity
 */
class ApplicationSubmissionListBuilder extends EntityListBuilder {


  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['id'] = $this->t('Application submission ID');
    $header['name'] = $this->t('Name');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /* @var $entity \Drupal\gla_opportunity\Entity\ApplicationSubmission */
    $row['id'] = $entity->id();
    $row['name'] = Link::createFromRoute(
      $entity->label(),
      'entity.application_submission.edit_form',
      ['application_submission' => $entity->id()]
    );
    return $row + parent::buildRow($entity);
  }

}
