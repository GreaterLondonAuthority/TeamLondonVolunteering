<?php

namespace Drupal\gla_search\Plugin\search_api\processor;

use Drupal\Core\Entity\EntityInterface;
use Drupal\gla_provider\ProviderProcessor;
use Drupal\search_api\Datasource\DatasourceInterface;
use Drupal\search_api\Item\ItemInterface;
use Drupal\search_api\Processor\ProcessorPluginBase;
use Drupal\search_api\Processor\ProcessorProperty;
use Drupal\search_api\SearchApiException;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Adds the item's associated provider profile to the indexed data.
 *
 * @SearchApiProcessor(
 *   id = "gla_search_add_provider_profile",
 *   label = @Translation("Provider organisation"),
 *   description = @Translation("Adds the item's associated provider profile to the indexed data."),
 *   stages = {
 *     "add_properties" = 0,
 *   },
 *   locked = false,
 *   hidden = false,
 * )
 */
class GlaSearchAddProviderProfile extends ProcessorPluginBase {

  /**
   * The current_user service used by this plugin.
   *
   * @var \Drupal\gla_provider\ProviderProcessor
   */
  protected $providerProcessor;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    /** @var static $processor */
    $processor = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $processor->setProviderProcessor($container->get('gla_provider.processor'));

    return $processor;
  }

  /**
   * Sets the provider processor.
   *
   * @param ProviderProcessor $provider_processor
   *
   * @return $this
   */
  public function setProviderProcessor(ProviderProcessor $provider_processor) {
    $this->providerProcessor = $provider_processor;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getPropertyDefinitions(DatasourceInterface $datasource = NULL) {
    $properties = [];

    if ($datasource && $datasource->getEntityTypeId() == 'node') {
      // ID.
      $definition = [
        'label' => $this->t('Provider profile ID'),
        'description' => $this->t('The ID of the provider profile'),
        'type' => 'integer',
        'processor_id' => $this->getPluginId(),
      ];
      $properties['gla_search_provider_profile'] = new ProcessorProperty($definition);

      // Name.
      $definition = [
        'label' => $this->t('Provider profile name'),
        'description' => $this->t('The name of the provider profile'),
        'type' => 'string',
        'processor_id' => $this->getPluginId(),
      ];
      $properties['gla_search_provider_profile_name'] = new ProcessorProperty($definition);
    }

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function addFieldValues(ItemInterface $item) {
    try {
      $entity = $item->getOriginalObject()->getValue();
    }
    catch (SearchApiException $e) {
      return;
    }

    if (!($entity instanceof EntityInterface)) {
      return;
    }

    $datasource_id = $item->getDatasourceId();

    $provider_profile = $this->providerProcessor->getProviderProfileFromEntity($entity);
    if ($provider_profile) {
      // ID.
      $nid = $provider_profile->id();
      $fields = $item->getFields(FALSE);
      $fields = $this->getFieldsHelper()
        ->filterForPropertyPath($fields, $datasource_id, 'gla_search_provider_profile');
      foreach ($fields as $field) {
        $field->addValue($nid);
      }

      // Name.
      $title = trim($provider_profile->getTitle());
      $fields = $item->getFields(FALSE);
      $fields = $this->getFieldsHelper()
        ->filterForPropertyPath($fields, $datasource_id, 'gla_search_provider_profile_name');
      foreach ($fields as $field) {
        $field->addValue($title);
      }
    }
  }

}
