#!/bin/bash

PROJECT_ROOT=$1
SETTINGS_DIR=$PROJECT_ROOT/web/sites/default
SERVICES_DIR=$PROJECT_ROOT/web/sites

# Change perms for local development.
sudo chmod 777 $SETTINGS_DIR

# Keep the settings.php that is generated but use the custom GLA structured ones.
if [ -f "$SETTINGS_DIR/settings.php" ]
then
  mv $SETTINGS_DIR/settings.php $SETTINGS_DIR/settings.php.old
fi

# Create the files directory if it doesn't exist.
if [ ! -d $SETTINGS_DIR/files ]; then
  mkdir -p $$SETTINGS_DIR/files;
fi

# Copy settings into place.
file=$SETTINGS_DIR/settings_gla.php
if [ -f "$file" ]
then
  echo "Settings already copied."
else
  cp $PROJECT_ROOT/config/drupal/local/settings/* $SETTINGS_DIR/
  # Change perms for local development.
  sudo chmod 777 $SETTINGS_DIR/settings*
fi

# Copy development services into place.
file=$SERVICES_DIR/development.services.yml
if [ -f "$file" ]
then
  echo "development.services already copied."
else
  cp $PROJECT_ROOT/config/drupal/local/services/development.services.yml $SERVICES_DIR/
fi

