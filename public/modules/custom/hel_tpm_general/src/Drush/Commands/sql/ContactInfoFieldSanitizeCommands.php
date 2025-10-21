<?php

namespace Drupal\hel_tpm_general\Drush\Commands\sql;

use Drupal\Component\Utility\Random;
use Drupal\Core\Database\Connection;
use Consolidation\AnnotatedCommand\CommandData;
use Consolidation\AnnotatedCommand\Hooks\HookManager;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldTypePluginManagerInterface;
use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;
use Drush\Drupal\Commands\sql\SanitizeCommands;
use Symfony\Component\Console\Input\InputInterface;

/**
 * A Drush command file.
 *
 * In addition to this file, you need a drush.services.yml
 * in root of your module, and a composer.json file that provides the name
 * of the services file to use.
 */
final class ContactInfoFieldSanitizeCommands extends DrushCommands {

  /**
   * {@inheritdoc}
   */
  public function __construct(
    protected Connection $database,
    protected EntityFieldManagerInterface $entityFieldManager,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected FieldTypePluginManagerInterface $fieldTypePluginManager,
  ) {
  }

  /**
   * Retrieves the database instance.
   *
   * @return mixed
   *   The database instance.
   */
  public function getDatabase() {
    return $this->database;
  }

  /**
   * Retrieves the entity field manager instance.
   *
   * @return mixed
   *   The entity field manager instance.
   */
  public function getEntityFieldManager() {
    return $this->entityFieldManager;
  }

  /**
   * Sanitize string fields associated with the contact info entity.
   */
  #[CLI\Hook(type: HookManager::POST_COMMAND_HOOK, target: SanitizeCommands::SANITIZE)]
  public function sanitize($result, CommandData $commandData): void {
    $options = $commandData->options();
    $conn = $this->getDatabase();
    $field_definitions = $this->getEntityFieldManager()->getFieldDefinitions('contact_info', 'contact_info');
    $field_storage = $this->getEntityFieldManager()->getFieldStorageDefinitions('contact_info');
    foreach (explode(',', $options['allowlist-fields']) as $key) {
      unset($field_definitions[$key], $field_storage[$key]);
    }

    $contact_info_ids = $this->database->select('contact_info', 'ci')
      ->fields('ci', ['id'])
      ->execute()
      ->fetchAll();
    foreach ($field_definitions as $key => $def) {
      $execute = FALSE;
      if (!isset($field_storage[$key]) || $field_storage[$key]->isBaseField()) {
        continue;
      }

      $tables = [
        'contact_info__' . $key,
        'contact_info_revision__' . $key,
      ];
      foreach ($contact_info_ids as $row) {

        foreach ($tables as $table) {
          $query = $conn->update($table);
          $query->condition('entity_id', $row->id);
          $name = $def->getName();
          $field_type_class = $this->fieldTypePluginManager->getPluginClass($def->getType());
          $supported_field_types = [
            'email',
            'string_long',
            'telephone',
            'text',
            'text_long',
            'text_with_summary',
          ];
          $generator = new Random();
          if (in_array($def->getType(), $supported_field_types)) {
            $value_array = $field_type_class::generateSampleValue($def);
            $value = substr($value_array['value'], 0, 30);
          }
          switch ($def->getType()) {
            case 'email':
              $query->fields([$name . '_value' => $value]);
              $execute = TRUE;
              break;

            case 'string':
              $query->fields([$name . '_value' => $generator->name()]);
              $execute = TRUE;
              break;

            case 'string_long':
              $query->fields([$name . '_value' => $value]);
              $execute = TRUE;
              break;

            case 'telephone':
              $query->fields([$name . '_value' => '15555555555']);
              $execute = TRUE;
              break;

            case 'text':
              $query->fields([$name . '_value' => $value]);
              $execute = TRUE;
              break;

            case 'text_long':
              $query->fields([$name . '_value' => $value]);
              $execute = TRUE;
              break;

            case 'text_with_summary':
              $query->fields([
                $name . '_value' => $value,
                $name . '_summary' => $value_array['summary'],
              ]);
              $execute = TRUE;
              break;
          }
          if ($execute) {
            $query->execute();
            $this->entityTypeManager->getStorage('contact_info')->resetCache();
          }
          else {
          }
        }
      }
      $this->logger()
        ->success(dt('Contact info field !field', ['!field' => $key]));
    }
  }

  /**
   * {@inheritdoc}
   */
  #[CLI\Hook(type: HookManager::ON_EVENT, target: SanitizeCommands::CONFIRMS)]
  public function messages(&$messages, InputInterface $input): void {
    $messages[] = dt('Sanitize text fields associated with contact info.');
  }

  /**
   * {@inheritdoc}
   */
  #[CLI\Hook(type: HookManager::OPTION_HOOK, target: SanitizeCommands::SANITIZE)]
  #[CLI\Option(name: 'allowlist-fields', description: 'A comma delimited list of fields exempt from sanitization.')]
  public function options($options = ['allowlist-fields' => '']): void {
  }

}
