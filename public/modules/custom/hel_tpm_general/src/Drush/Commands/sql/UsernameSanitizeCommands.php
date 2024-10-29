<?php

namespace Drupal\hel_tpm_general\Drush\Commands\sql;

use Consolidation\AnnotatedCommand\CommandData;
use Consolidation\AnnotatedCommand\Hooks\HookManager;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;
use Drush\Drupal\Commands\sql\SanitizeCommands;
use Drush\Sql\SqlBase;
use Symfony\Component\Console\Input\InputInterface;

/**
 * A Drush commandfile.
 *
 * In addition to this file, you need a drush.services.yml
 * in root of your module, and a composer.json file that provides the name
 * of the services file to use.
 */
final class UsernameSanitizeCommands extends DrushCommands {

  /**
   * Constructs a HelTpmGeneralCommands object.
   */
  public function __construct(
    protected Connection $database,
    protected EntityTypeManagerInterface $entityTypeManager
  ) {
    parent::__construct();
  }

  /**
   * Sanitize user names.
   */
  #[CLI\Hook(type: HookManager::POST_COMMAND_HOOK, target: 'sql-sanitize')]
  public function sanitize($result, CommandData $commandData): void {
    $options = $commandData->options();
    $query = $this->database->update('users_field_data')->condition('uid', 0, '>');
    $messages = [];

    // Sanitize usernames.
    if ($this->isEnabled($options['sanitize-name'])) {
      if (str_contains($options['sanitize-name'], '%')) {
        // We need a different sanitization query for MSSQL, Postgres and Mysql.
        $sql = SqlBase::create($commandData->input()->getOptions());
        $db_driver = $sql->scheme();
        if ($db_driver == 'pgsql') {
          $username_map = [
            '%uid' => "' || uid || '",
            '%mail' => "' || replace(mail, '@', '_') || '",
            '%name' => "' || replace(name, ' ', '_') || '",
          ];
          $new_username = "'" . str_replace(array_keys($username_map), array_values($username_map), $options['sanitize-name']) . "'";
        }
        elseif ($db_driver == 'mssql') {
          $username_map = [
            '%uid' => "' + uid + '",
            '%mail' => "' + replace(mail, '@', '_') + '",
            '%name' => "' + replace(name, ' ', '_') + '",
          ];
          $new_username = "'" . str_replace(array_keys($username_map), array_values($username_map), $options['sanitize-name']) . "'";
        }
        else {
          $username_map = [
            '%uid' => "', uid, '",
            '%mail' => "', replace(mail, '@', '_'), '",
            '%name' => "', replace(name, ' ', '_'), '",
          ];
          $new_username = "concat('" . str_replace(array_keys($username_map), array_values($username_map), $options['sanitize-name']) . "')";
        }
        $query->expression('name', $new_username);
      }
      else {
        $query->fields(['name' => $options['sanitize-name']]);
      }
      $messages[] = dt('User names sanitized.');
    }

    if (!empty($options['ignored-roles'])) {
      $roles = explode(',', $options['ignored-roles']);
      /** @var \Drupal\Core\Database\Query\SelectInterface $roles_query */
      $roles_query = $this->database->select('user__roles', 'ur');
      $roles_query
        ->condition('roles_target_id', $roles, 'IN')
        ->fields('ur', ['entity_id']);
      $roles_query_results = $roles_query->execute();
      $ignored_users = $roles_query_results->fetchCol();

      if (!empty($ignored_users)) {
        $query->condition('uid', $ignored_users, 'NOT IN');
        $messages[] = dt('User names for the specified roles preserved.');
      }
    }

    if ($messages) {
      $query->execute();
      $this->entityTypeManager->getStorage('user')->resetCache();
      foreach ($messages as $message) {
        $this->logger()->success($message);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  #[CLI\Hook(type: HookManager::OPTION_HOOK, target: SanitizeCommands::SANITIZE)]
  #[CLI\Option(name: 'sanitize-name', description: 'The pattern for test user names in the sanitization operation, or <info>no</info> to keep user names unchanged. May contain replacement patterns <info>%uid</info>, <info>%mail</info> or <info>%name</info>.')]
  #[CLI\Option(name: 'ignored-roles', description: 'A comma delimited list of roles. Users with at least one of the roles will be exempt from sanitization.')]
  public function options($options = ['sanitize-name' => 'user+%uid', 'ignored-roles' => NULL]): void {
  }

  /**
   * {@inheritdoc}
   */
  #[CLI\Hook(type: HookManager::ON_EVENT, target: 'sql-sanitize-confirms')]
  public function messages(&$messages, InputInterface $input): void {
    $options = $input->getOptions();
    if ($this->isEnabled($options['sanitize-name'])) {
      $messages[] = dt('Sanitize user names.');
    }
  }

  /**
   * Test an option value to see if it is disabled.
   */
  protected function isEnabled(?string $value): bool {
    return $value != 'no' && $value != '0';
  }

}
