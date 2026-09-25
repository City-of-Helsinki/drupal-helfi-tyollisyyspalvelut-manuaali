<?php

declare(strict_types=1);

namespace Drupal\hel_tpm_update_reminder\Plugin\Action;

use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Logger\LoggerChannelTrait;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\hel_tpm_update_reminder\UpdateReminderUtility;
use Drupal\views_bulk_operations\Action\ViewsBulkOperationsActionBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a Reset Update Reminder action.
 */
#[Action(
  id: 'hel_tpm_update_reminder_reset_update_reminder',
  label: new TranslatableMarkup('Reset Update Reminder'),
  type: 'node'
)]
final class ResetUpdateReminderAction extends ViewsBulkOperationsActionBase implements ContainerFactoryPluginInterface {

  use LoggerChannelTrait;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    private readonly StateInterface $state,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new self(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('state')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function access($entity, ?AccountInterface $account = NULL, $return_as_object = FALSE): AccessResultInterface|bool {
    return $account->hasPermission('reset update reminder');
  }

  /**
   * {@inheritdoc}
   */
  public function execute(?ContentEntityInterface $entity = NULL): void {
    UpdateReminderUtility::clearMessagesSent((int) $entity->id());
    $this->getLogger('hel_tpm_update_reminder_reset_update_reminder')->notice('Reset update reminders for @node', ['@node' => $entity->label()]);
  }

}
