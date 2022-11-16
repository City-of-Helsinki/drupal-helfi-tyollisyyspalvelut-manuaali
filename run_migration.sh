#!/bin/bash

drush cim -y
drush migrate-reset-status organisation_import
drush migrate-reset-status service_wp_import
drush migrate-reset-status service_groupcontent
drush migrate-rollback organisation_import
drush migrate-rollback service_wp_import
drush migrate-rollback service_groupcontent
drush migrate-import organisation_import
drush migrate-import service_wp_import
drush migrate-import service_groupcontent
