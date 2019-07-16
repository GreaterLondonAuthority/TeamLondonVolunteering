# TeamLondon

---

Team London is a Drupal 8 website based on the [“Composer template for Drupal projects”](https://github.com/drupal-composer/drupal-project).

The composer build process (composer install) for Team London requires 2 GLA composer “estate features” that are planned to be open sourced in the future (gla/gla_estate_d8 & gla/profile_d8). Other build requirements include composer and npm.

In order to build the Team London application (before these features are made available) these 2 “estate features” they need to be removed/substituted in composer.json.

Note: All environment specific configs and scripts have been removed from the open source fork.

---

## DrupalVM setup

### Site setup instructions

###### Assuming setup on linux
- https://www.drupalvm.com/
- Download vagrant and virtual box (latest versions)
- Drupalvm is required in the site's composer.json so there is no need to download it separately
- Clone this repo down and cd into the directory created
- Run `composer install`
- If you need to make any changes to the drupalvm config, copy config/vm/sample.local.config.yml to config/vm/local.config.yml and add them there
- Still in the project root, run `vagrant up`
- The vm may install additional plugins in which case you'll need to run `vagrant up` again
- The vm will then provision. This may take a few minutes
- Once complete, the dashboard URL will be printed
- Default site login details are admin@example.com / password

### Overview of drupalvm config
###### Check the config/vm/config.yml file for up-to-date information
- Uses nginx but apache config is also there so can be configured as desired
- Sets up aliases
- Installs adminer, drush, drupalconsole, mailhog, nodejs, pimpmylog, solr, xdebug
- Uses php7.2
- Installs and enables xdebug
---


