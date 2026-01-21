<?php

declare(strict_types=1);

namespace Drupal\hel_tpm_general\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\State\StateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure Helsinki TPM General settings for this site.
 */
final class FeedbackLinkSettingsForm extends ConfigFormBase {

  /**
   * State interface.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  private StateInterface $state;

  /**
   * {@inheritdoc}
   */
  public function __construct(ConfigFactoryInterface $config_factory, $typedConfigManager, StateInterface $state) {
    parent::__construct($config_factory, $typedConfigManager);
    $this->state = $state;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('config.typed'),
      $container->get('state')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'hel_tpm_general_feedback_link_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['feedback_link_url'] = [
      '#type' => 'url',
      '#title' => $this->t('Feedback link URL'),
      '#default_value' => $this->state->get('hel_tpm_general.feedback_link_url'),
    ];
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->state->set('hel_tpm_general.feedback_link_url', $form_state->getValue('feedback_link_url'));
    parent::submitForm($form, $form_state);
  }

}
