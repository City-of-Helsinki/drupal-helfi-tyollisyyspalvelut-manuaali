#!/bin/bash

cd /var/www/html/public && drush deploy

drush locale:import --type=customized --override=none "fi" "../translations/fi-interface-translations.po" || true
drush locale:import --type=customized --override=none "sv" "../translations/sv-interface-translations.po" || true
