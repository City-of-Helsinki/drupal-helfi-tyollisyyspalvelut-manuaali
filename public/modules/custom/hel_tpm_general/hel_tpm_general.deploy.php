<?php

/**
 * @file
 * Deploy functions for Hel TPM General.
 */

/**
 * Rebuild node access after upgrading the Nodeaccess contrib module.
 */
function hel_tpm_general_deploy_0001(array &$sandbox): void {
  node_access_rebuild();
}
