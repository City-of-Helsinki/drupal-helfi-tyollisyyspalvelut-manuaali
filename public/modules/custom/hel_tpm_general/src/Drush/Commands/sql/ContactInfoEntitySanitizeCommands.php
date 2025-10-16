<?php

namespace Drupal\hel_tpm_general\Drush\Commands\sql;

use Consolidation\AnnotatedCommand\CommandData;
use Consolidation\AnnotatedCommand\Hooks\HookManager;
use Drupal\Component\Utility\Random;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;
use Symfony\Component\Console\Input\InputInterface;

/**
 * A Drush command file.
 *
 * In addition to this file, you need a drush.services.yml
 * in root of your module, and a composer.json file that provides the name
 * of the services file to use.
 */
final class ContactInfoEntitySanitizeCommands extends DrushCommands {

  /**
   * Constructs a HelTpmGeneralCommands object.
   */
  public function __construct(
    protected Connection $database,
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {
    parent::__construct();
  }

  /**
   * Sanitize user names.
   */
  #[CLI\Hook(type: HookManager::POST_COMMAND_HOOK, target: 'sql-sanitize')]
  public function sanitize($result, CommandData $commandData): void {
    $tables = ['contact_info_field_data', 'contact_info_field_revision'];
    $generator = new Random();
    foreach ($tables as $table) {
      $query = $this->database->update($table);
      $messages = [];
      $value = $generator->name();
      $query->fields(['title' => $value]);
      $messages[] = dt('Contact info sanitized.');

      if ($messages) {
        $query->execute();
        $this->entityTypeManager->getStorage('contact_info')->resetCache();
        foreach ($messages as $message) {
          $this->logger()->success($message);
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  #[CLI\Hook(type: HookManager::ON_EVENT, target: 'sql-sanitize-confirms')]
  public function messages(&$messages, InputInterface $input): void {
    $messages[] = dt('Sanitize contact info entities.');
  }

  /**
   * Test an option value to see if it is disabled.
   */
  protected function isEnabled(?string $value): bool {
    return $value != 'no' && $value != '0';
  }

}
