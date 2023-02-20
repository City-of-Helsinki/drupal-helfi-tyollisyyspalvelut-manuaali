<?php

use Drupal\node\NodeInterface;
use Drupal\node\NodeStorageInterface;

$outputFile = '/tmp/organizations.json';

convertCsvToJson($outputFile);
$json = file_get_contents($outputFile);
$json_decoded = json_decode($json);

migrateLocations($json_decoded);

/**
 * Migration function.
 *
 * @param $json_decoded
 *
 * @return void
 * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
 * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
 * @throws \Drupal\Core\Entity\EntityStorageException
 */
function migrateLocations($json_decoded) {
  unset($json_decoded[0]);
  $storage = \Drupal::entityTypeManager()->getStorage('node');

  foreach ($json_decoded as $location) {
    $name = createName($location);
    if (empty($name)) {
      continue;
    }
    $location_node = getLocationByName($name, $storage);
    if (empty($location_node)) {
      $location_node = $storage->create([
        'title' => $name,
        'type' => 'service_location'
      ]);
    }
    $address = [
      'country_code' => 'FI',
      'organization' => !empty($location[3]) ? $location[3] : '',
      'address_line1' => !empty($location[4]) ? $location[4] : '',
      'postal_code' => !empty($location[5]) ? formatPostalCode($location[5]) : '',
      'locality' => !empty($location[6]) ? $location[6] : ''
    ];
    $location_node->field_address = $address;

    $location_node->field_accessibility = createAccessibilityData($location);
    $location_node->field_accessibility_details = !empty($location[8]) ? $location[8] : '';
    $location_node->save();
    addGroupMembership($location[0], $location_node);
  }
}

function formatPostalCode($postal_code) {
  $len = strlen($postal_code);
  if ($len > 5) {
    $postal_code = substr($postal_code, 0, 5);
  }
  // Probably because of excel stripping prefixing zeroes ego 00580 -> 580
  if ($len < 5) {
    $postal_code = str_pad($postal_code, 5, '0', STR_PAD_LEFT);
  }
  return $postal_code;
}
/**
 * @param $group_name
 * @param \Drupal\node\NodeStorageInterface $location_node
 *
 * @var array $groups
 * @var \Drupal\group\Entity\GroupInterface $group
 *
 * @return void
 * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
 * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
 */
function addGroupMembership($group_name, NodeInterface $location_node) {
  $groups = \Drupal::entityTypeManager()->getStorage('group')->loadByProperties(['label' => $group_name]);
  if (empty($groups)) {
    return;
  }
  $plugin_id = 'group_node:service_location';
  $group = reset($groups);
  $membership_exists = $group->getContentByEntityId($plugin_id, $location_node->id());
  if ($membership_exists) {
    return;
  }

  $group->addContent($location_node, $plugin_id);
}



/**
 * Create references and missing terms.
 *
 * @param $location
 *
 * @return array
 * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
 * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
 * @throws \Drupal\Core\Entity\EntityStorageException
 */
function createAccessibilityData($location) {
  $data = [];
  $storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
  $accessibility = !empty($location[7]) ? explode(',', $location[7]) : [];
  if (empty($accessibility)) {
    return [];
  }
  foreach ($accessibility as $key => $value) {
    $value = trim($value);
    $value = rtrim($value, ".");
    if (strlen($value) <= 0) {
      continue;
    }
    $term = $storage->loadByProperties(['name' => $value, 'vid' => 'location_accessibility']);
    if (empty($term)) {
      $term = $storage->create([
        'name' => $value,
        'vid' => 'location_accessibility'
      ]);
      $term->save();
    }
    else {
      $term = reset($term);
    }
    $data[] = ['target_id' => $term->id()];
  }
  return $data;
}

/**
 * Get location by name.
 *
 * @param $name
 * @param \Drupal\node\NodeStorageInterface $storage
 *
 * @return \Drupal\Core\Entity\EntityInterface|false|null
 */
function getLocationByName($name, NodeStorageInterface $storage) {
  $locations = $storage->loadByProperties([
    'type' => 'service_location',
    'title' => $name
  ]);
  if (empty($locations)) {
    return NULL;
  }
  return reset($locations);
}

/**
 * Create name.
 *
 * @param $location
 *
 * @return mixed|string
 */
function createName($location) {
  if (empty($location[4]) || empty($location[5] || $location[6])) {
    return NULL;
  }
  $address = sprintf("%s %s %s", $location[4], formatPostalCode($location[5]), $location[6]);
  return !empty($location[3]) ? sprintf("%s (%s)",$location[3], $address) : $address;
}

/**
 * Convert csv to json.
 *
 * @param $outputFile
 *
 * @return void
 */
function convertCsvToJson($outputFile) {
  $fileHandle = fopen("../scripts/organisaatiot-toimipisteineen.csv","r");
// Initialize an array to hold the CSV data
  $data = array();

// Loop through each row in the CSV file and add it to the data array
  while (!feof($fileHandle)) {
    $data[] = fgetcsv($fileHandle, NULL, ';', "'");
  }

// Close the CSV file
  fclose($fileHandle);

// Convert the data array to a JSON object
  $json = json_encode($data);

// Write the JSON output to a file
  file_put_contents($outputFile, $json);
}