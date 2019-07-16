<?php

namespace Drupal\gla_core\Plugin\FeaturesAssignment;

use Drupal\features\Plugin\FeaturesAssignment\FeaturesAssignmentOptionalType;

/**
 * Class for assigning configuration to the
 * InstallStorage::CONFIG_OPTIONAL_DIRECTORY based on entity types.
 *
 * @Plugin(
 *   id = "optional",
 *   weight = 0,
 *   name = @Translation("Optional type"),
 *   description = @Translation("Assign designated types of configuration to the 'config/optional' install directory. For example, if views are selected as optional, views assigned to any feature will be exported to the 'config/optional' directory and will not create a dependency on the Views module."),
 *   config_route_name = "features.assignment_optional",
 *   default_settings = {
 *     "types" = {
 *       "config" = {},
 *     }
 *   }
 * )
 */
class GlaFeaturesAssignmentOptionalType extends FeaturesAssignmentOptionalType {

  /**
   * {@inheritdoc}
   */
  public function assignPackages($force = FALSE) {

    // We implement this plugin so that all feature config is exported as
    // 'optional'. This is required because of the trash and rebuild approach
    // because features' dependencies on each other can become messy easily.
    // This way, when the features are installed during gla_core_install() on a
    // fresh installation, their config doesn't have to be imported at
    // installation (if dependencies aren't met) but is then installed when the
    // module is reverted.
    // We do this here rather than using the bundle assignment options so that
    // any new exportable config is automatically handled.
    // This is called when:
    // - loading the main feature list page
    // - loading a feature's edit page
    // - exporting via the UI
    // - exporting via drush.

    $current_bundle = $this->assigner->getBundle();
    $config_collection = $this->featuresManager->getConfigCollection();

    // Set all exportable types as 'optional'.
    $settings = $current_bundle->getAssignmentSettings($this->getPluginId());
    foreach ($config_collection as &$item) {
      $type = $item->getType();
      if (!isset($settings['types']['config'][$type])) {
        $settings['types']['config'][$type] = $type;
      }
    }

    // Some (all?) system.simple config isn't registered if it's in the
    // optional directory. Therefore we unset it here so it's using the
    // install directory.
    // TODO: Look into this properly. Doesn't seem to be related to module weight.
    if (isset($settings['types']['config']['system_simple'])) {
      unset($settings['types']['config']['system_simple']);
    }

    $current_bundle->setAssignmentSettings($this->getPluginId(), $settings);

    // Run the default implementation.
    parent::assignPackages($force);
  }

}
