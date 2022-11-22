#!/bin/bash

run_drush () {
  echo "### Running command $@"
  drush $@
  # Sleep after each command to avoid any race conditions.
  sleep 2
}

run_drush cim -y
run_drush migrate-reset-status organisation_import
run_drush migrate-reset-status service_wp_import
run_drush migrate-reset-status service_groupcontent
run_drush migrate-rollback organisation_import
run_drush migrate-rollback service_wp_import
run_drush migrate-rollback service_groupcontent
run_drush migrate-import organisation_import
run_drush migrate-import service_wp_import
run_drush migrate-import service_groupcontent
