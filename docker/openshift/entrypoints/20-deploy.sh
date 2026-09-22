#!/bin/bash

source /init.sh

cd /var/www/html/public

if [ ! -n "$OPENSHIFT_BUILD_NAME" ]; then
  echo "OPENSHIFT_BUILD_NAME is not defined. Exiting early."
  exit 1
fi


if deployment_in_progress; then
  echo "Deployment already in progress"
  exit 1
fi

echo "Starting deploy: $(date)"
# Populate deploy ID so 20-deploy.sh is skipped.
set_deploy_id $OPENSHIFT_BUILD_NAME

drush state:set system.maintenance_mode 1 --input-format=integer
# Run maintenance tasks (config import, database updates etc)

drush deploy
deploy_exit_code=$?

# Run locale imports.
drush locale:import --type=customized --override=none "fi" "../translations/fi-interface-translations.po" || true
drush locale:import --type=customized --override=none "sv" "../translations/sv-interface-translations.po" || true

# Disable maintenance mode
drush state:set system.maintenance_mode 0 --input-format=integer

# Exit with failure if drush deploy failed
if [ $deploy_exit_code -ne 0 ]; then
    exit 1
fi
