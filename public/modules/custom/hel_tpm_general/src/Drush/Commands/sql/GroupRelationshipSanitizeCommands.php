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
final class GroupRelationshipSanitizeCommands extends DrushCommands {

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
    $table = 'group_relationship_field_data';
    $generator = new Random();
    $query = $this->database->update($table);
    $query->condition('type', '%group_membership%', 'LIKE');
    $messages = [];
    $value = $generator->name();
    $query->fields(['label' => $value]);
    $messages[] = dt('Group Memberships sanitized.');

    if ($messages) {
      $query->execute();
      $this->entityTypeManager->getStorage('group_content')->resetCache();
      foreach ($messages as $message) {
        $this->logger()->success($message);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  #[CLI\Hook(type: HookManager::ON_EVENT, target: 'sql-sanitize-confirms')]
  public function messages(&$messages, InputInterface $input): void {
    $messages[] = dt('Sanitize group relationship entities.');
  }

}
