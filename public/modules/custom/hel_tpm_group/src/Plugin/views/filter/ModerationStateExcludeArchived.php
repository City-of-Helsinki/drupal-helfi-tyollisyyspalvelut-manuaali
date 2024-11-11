<?php

declare(strict_types = 1);

namespace Drupal\hel_tpm_group\Plugin\views\filter;

use Drupal\content_moderation\Plugin\views\filter\ModerationStateFilter;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a filter for the moderation state and excludes archived by default.
 *
 * @ingroup views_filter_handlers
 *
 * @ViewsFilter("moderation_state_filter_exclude_archived")
 */
#[ViewsFilter("moderation_state_filter_exclude_archived")]
class ModerationStateExcludeArchived extends ModerationStateFilter {

  /**
   * {@inheritdoc}
   */
  public function getValueOptions() {
    $parentValueOptions = parent::getValueOptions();
    $newValueOption = [
      'all_exclude_archived' => $this->t("– All excluding archived –"),
    ];
    $this->valueOptions = $newValueOption + $parentValueOptions;
    return $this->valueOptions;
  }

  /**
   * {@inheritdoc}
   */
  public function acceptExposedInput($input) {
    if ($input[$this->getExposedIdentifier()] === 'all_exclude_archived') {
      return TRUE;
    }
    else {
      return parent::acceptExposedInput($input);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function buildExposedForm(&$form, FormStateInterface $form_state): void {
    parent::buildExposedForm($form, $form_state);
    $form[$this->getExposedIdentifier()]['#default_value'] = 'all_exclude_archived';
    // Remove the 'All' option.
    unset($form[$this->getExposedIdentifier()]['#options']['All']);
  }

  /**
   * {@inheritdoc}
   */
  protected function opSimple() {
    if (empty($this->value)) {
      $this->value = [
        'service_moderation-draft' => "service_moderation-draft",
        'service_moderation-ready_to_publish' => "service_moderation-ready_to_publish",
        'service_moderation-published' => "service_moderation-published",
        'service_moderation-outdated' => "service_moderation-outdated",
      ];
    }
    parent::opSimple();
  }

  /**
   * {@inheritdoc}
   */
  protected function valueForm(&$form, FormStateInterface $form_state): void {
    parent::valueForm($form, $form_state);
    if (!$form_state->get('exposed')) {
      return;
    }
    $form['value']['#default_value'] = 'all_exclude_archived';
    unset($form['value']['#options']['all']);
    $identifier = $this->getExposedIdentifier();
    $userInput = $form_state->getUserInput();
    if (!empty($identifier) && !empty($userInput) && $userInput[$identifier] == 'All') {
      // Replace previously set default input value.
      $userInput[$identifier] = 'all_exclude_archived';
      $form_state->setUserInput($userInput);
    }
  }

  protected function getExposedIdentifier(): string {
    return $this->options['expose']['identifier'];
  }
}
