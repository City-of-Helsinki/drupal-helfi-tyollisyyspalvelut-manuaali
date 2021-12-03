<?php

namespace Drupal\hel_tpm_contact_info\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form controller for the contact info entity edit forms.
 */
class ContactInfoForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {

    $entity = $this->getEntity();
    $result = $entity->save();
    $link = $entity->toLink($this->t('View'))->toRenderable();

    $message_arguments = ['%label' => $this->entity->label()];
    $logger_arguments = $message_arguments + ['link' => render($link)];

    if ($result == SAVED_NEW) {
      $this->messenger()->addStatus($this->t('New contact info %label has been created.', $message_arguments));
      $this->logger('hel_tpm_contact_info')->notice('Created new contact info %label', $logger_arguments);
    }
    else {
      $this->messenger()->addStatus($this->t('The contact info %label has been updated.', $message_arguments));
      $this->logger('hel_tpm_contact_info')->notice('Updated new contact info %label.', $logger_arguments);
    }

    $form_state->setRedirect('entity.contact_info.canonical', ['contact_info' => $entity->id()]);
  }

}
