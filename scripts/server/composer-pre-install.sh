#!/bin/bash
# 22/01/2018 by user2 & user2 - initial Composer build

printf '%s\n' --------------------
echo "running pre composer deploy script"
echo "allow composer to read from <private-packagist>"

# drupal core fails to download with the default timeout
echo "extend composer timeout to 180s"
composer config --global process-timeout 18000

echo "update composer mirrors"
composer update mirrors

