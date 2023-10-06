<?php

namespace Drupal\hel_tpm_url_shortener\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\RendererInterface;

/**
 * Form controller for the shortenerredirect entity edit forms.
 */
class ShortenerredirectForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {

    $entity = $this->getEntity();
    $result = $entity->save();
    $link = $entity->toLink($this->t('View'))->toRenderable();

    $message_arguments = ['%label' => $this->entity->label()];
    $logger_arguments = $message_arguments + ['link' => RendererInterface::render($link)];

    if ($result == SAVED_NEW) {
      $this->logger('hel_tpm_url_shortener')->notice('Created new shortenerredirect %label', $logger_arguments);
    }
    else {
      $this->logger('hel_tpm_url_shortener')->notice('Updated new shortenerredirect %label.', $logger_arguments);
    }

    $form_state->setRedirect('entity.shortenerredirect.canonical', ['shortenerredirect' => $entity->id()]);
  }

}
