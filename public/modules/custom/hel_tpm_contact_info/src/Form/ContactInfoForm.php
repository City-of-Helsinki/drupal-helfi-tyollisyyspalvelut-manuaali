<?php

namespace Drupal\hel_tpm_contact_info\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;

/**
 * Form controller for the contact info entity edit forms.
 */
class ContactInfoForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state): int {
    $result = $this->entity->save();

    $messageArguments = [
      '@type' => $this->entity->getEntityType()->getLabel(),
      '%title' => $this->entity->label(),
    ];
    $loggerArguments = $messageArguments + ['link' => Link::fromTextAndUrl($this->t('View'), $this->entity->toUrl())->toString()];
    if ($result === SAVED_NEW) {
      $this->messenger()->addStatus($this->t('@type %title has been created.', $messageArguments));
      $this->logger('hel_tpm_contact_info')->notice('@type %title has been created.', $loggerArguments);
    }
    else {
      $this->messenger()->addStatus($this->t('@type %title has been updated.', $messageArguments));
      $this->logger('hel_tpm_contact_info')->notice('@type %title has been updated.', $loggerArguments);
    }

    $form_state->setRedirect('entity.contact_info.canonical', ['contact_info' => $this->entity->id()]);
    return $result;
  }

}
