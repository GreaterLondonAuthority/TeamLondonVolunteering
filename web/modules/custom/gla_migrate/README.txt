Migration setup
---


Development
---

Whenever a change is made on any file inside the config install folder you will have to run the following drush command to reimport any changes:
drush config-import --partial --source="modules/custom/gla_migrate/config/install" -y


If something doesn't work it's best to just roll back the migration and reimport all items.

Development on migrations requires a lot of config import, drush cache rebuild, and item rollback and reimport.

drush ms
drush mrs MIGRATION_NAME
drush mr MIGRATION_NAME
drush mim MIGRATION_NAME --limit=X

GLA
---

Run migrations in this order:
drush mim gla_providers

drush mim gla_provider_profiles

drush mim gla_opportunities

drush mim gla_provider_groups

drush mim gla_volunteers

drush mim gla_provider_profiles_address
drush mim gla_provider_profiles_url
drush mim gla_provider_profiles_phone

drush mim gla_opportunities_address
drush mim gla_opportunities_days_times
