<?php

namespace Drupal\hel_tpm_better_locale_interface\Form;

use Drupal\Component\Gettext\PoItem;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\locale\Form\TranslateFormBase;
use Drupal\locale\SourceString;

/**
 * Provides an enhanced translation edit form supporting all languages.
 */
class BetterTranslateEditForm extends TranslateFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'hel_tpm_better_translate_edit_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $filter_values = $this->translateFilterValues();
    $langcode = $filter_values['langcode'];
    $is_all = empty($langcode) || $langcode === 'all';

    $this->languageManager->reset();
    $languages = $this->languageManager->getLanguages();

    if ($is_all) {
      $target_languages = [];
      foreach ($languages as $code => $language) {
        if (locale_is_translatable($code)) {
          $target_languages[$code] = $language;
        }
      }
      if (empty($target_languages)) {
        $target_languages = $languages;
      }
    }
    else {
      $target_languages = isset($languages[$langcode]) ? [$langcode => $languages[$langcode]] : [];
    }

    $header = [$this->t('Source string')];
    foreach ($target_languages as $target_langcode => $target_language) {
      $header[] = $this->t('Translation for @language', ['@language' => $target_language->getName()]);
    }

    $form['#attached']['library'][] = 'locale/drupal.locale.admin';

    $form['langcode'] = [
      '#type' => 'value',
      '#value' => $filter_values['langcode'],
    ];

    $form['strings'] = [
      '#type' => 'table',
      '#tree' => TRUE,
      '#header' => $header,
      '#empty' => $this->t('No strings available.'),
      '#attributes' => ['class' => ['locale-translate-edit-table']],
    ];

    if (!empty($target_languages)) {
      $strings = $this->translateFilterLoadStrings();

      // Preload all existing translations for the loaded strings.
      $lids = [];
      foreach ($strings as $string) {
        $lids[] = $string->getId();
      }
      $lids = array_values(array_unique(array_filter($lids)));

      $translations_by_lid_and_lang = [];
      if (!empty($lids)) {
        $conditions = ['lid' => $lids, 'translated' => TRUE];
        if (!$is_all) {
          $conditions['language'] = $langcode;
        }
        $existing_translations = $this->localeStorage->getTranslations($conditions);
        foreach ($existing_translations as $existing_translation) {
          $translations_by_lid_and_lang[$existing_translation->lid][$existing_translation->language] = $existing_translation;
        }
      }

      foreach ($strings as $string) {
        $lid = $string->getId();
        if (isset($form['strings'][$lid])) {
          continue;
        }

        // Cast into source string, will do for our purposes.
        $source = new SourceString($string);
        // Split source to work with plural values.
        $source_array = $source->getPlurals();
        if (count($source_array) == 1) {
          // Add original string value and mark as non-plural.
          $plural = FALSE;
          $form['strings'][$lid]['original'] = [
            '#type' => 'item',
            '#title' => $this->t('Source string (@language)', ['@language' => $this->t('Built-in English')]),
            '#title_display' => 'invisible',
            '#plain_text' => $source_array[0],
            '#prefix' => '<span lang="en">',
            '#suffix' => '</span>',
          ];
        }
        else {
          // Add original string value and mark as plural.
          $plural = TRUE;
          $original_singular = [
            '#type' => 'item',
            '#title' => $this->t('Singular form'),
            '#plain_text' => $source_array[0],
            '#prefix' => '<span class="visually-hidden">' . $this->t('Source string (@language)', ['@language' => $this->t('Built-in English')]) . '</span><span lang="en">',
            '#suffix' => '</span>',
          ];
          $original_plural = [
            '#type' => 'item',
            '#title' => $this->t('Plural form'),
            '#plain_text' => $source_array[1],
            '#prefix' => '<span lang="en">',
            '#suffix' => '</span>',
          ];
          $form['strings'][$lid]['original'] = [
            $original_singular,
            ['#markup' => '<br>'],
            $original_plural,
          ];
        }
        if (!empty($string->context)) {
          $form['strings'][$lid]['original'][] = [
            '#type' => 'inline_template',
            '#template' => '<br><small>{{ context_title }}: <span lang="en">{{ context }}</span></small>',
            '#context' => [
              'context_title' => $this->t('In Context'),
              'context' => $string->context,
            ],
          ];
        }

        // Approximate the number of rows to use in the default textarea.
        $rows = min(ceil(str_word_count($source_array[0]) / 12), 10);

        foreach ($target_languages as $target_langcode => $target_language) {
          $language_name = $target_language->getName();
          $plurals = $this->getNumberOfPlurals($target_langcode);
          $translation_object = $translations_by_lid_and_lang[$lid][$target_langcode] ?? NULL;
          $translation_array = $translation_object ? $translation_object->getPlurals() : [];

          if (!$plural) {
            $form['strings'][$lid][$target_langcode][0] = [
              '#type' => 'textarea',
              '#title' => $this->t('Translated string (@language)', ['@language' => $language_name]),
              '#title_display' => 'invisible',
              '#rows' => $rows,
              '#default_value' => $translation_array[0] ?? '',
              '#attributes' => ['lang' => $target_langcode],
            ];
          }
          else {
            // Add a textarea for each plural variant.
            for ($i = 0; $i < $plurals; $i++) {
              $form['strings'][$lid][$target_langcode][$i] = [
                '#type' => 'textarea',
                // @todo Should use better labels https://www.drupal.org/node/2499639
                '#title' => ($i == 0 ? $this->t('Singular form') : $this->formatPlural($i, 'First plural form', '@count. plural form')),
                '#rows' => $rows,
                '#default_value' => $translation_array[$i] ?? '',
                '#attributes' => ['lang' => $target_langcode],
                '#prefix' => $i == 0 ? ('<span class="visually-hidden">' . $this->t('Translated string (@language)', ['@language' => $language_name]) . '</span>') : '',
              ];
            }
            if ($plurals == 2) {
              // Simplify interface text for the most common case.
              $form['strings'][$lid][$target_langcode][1]['#title'] = $this->t('Plural form');
            }
          }
        }
      }

      if (count(Element::children($form['strings']))) {
        $form['actions'] = ['#type' => 'actions'];
        $form['actions']['submit'] = [
          '#type' => 'submit',
          '#value' => $this->t('Save translations'),
        ];
      }
    }
    $form['pager']['#type'] = 'pager';
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  protected function translateFilters() {
    $filters = parent::translateFilters();
    $filters['langcode']['default'] = 'all';
    $filters['langcode']['options'] = ['all' => $this->t('All')] + $filters['langcode']['options'];
    return $filters;
  }

  /**
   * {@inheritdoc}
   */
  protected function translateFilterLoadStrings() {
    $filter_values = $this->translateFilterValues();
    $langcode = $filter_values['langcode'];

    if (!empty($langcode) && $langcode !== 'all') {
      return parent::translateFilterLoadStrings();
    }

    $conditions = [];
    $options = ['pager limit' => 30, 'translated' => TRUE, 'untranslated' => TRUE];

    // Add translation status conditions and options.
    switch ($filter_values['translation']) {
      case 'translated':
        $conditions['translated'] = TRUE;
        if ($filter_values['customized'] != 'all') {
          $conditions['customized'] = $filter_values['customized'];
        }
        break;

      case 'untranslated':
        $conditions['translated'] = FALSE;
        break;
    }

    if (!empty($filter_values['string'])) {
      $options['filters']['source'] = $filter_values['string'];
    }

    return $this->localeStorage->getStrings($conditions, $options);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $strings = $form_state->getValue('strings', []);
    if (!is_array($strings)) {
      return;
    }

    foreach ($strings as $lid => $values) {
      if (!is_array($values)) {
        continue;
      }
      foreach ($values as $langcode => $translations) {
        if ($langcode === 'original' || !is_array($translations)) {
          continue;
        }
        foreach ($translations as $key => $value) {
          if (!locale_string_is_safe($value)) {
            $form_state->setErrorByName("strings][$lid][$langcode][$key", $this->t('The submitted string contains disallowed HTML: %string', ['%string' => $value]));
            $form_state->setErrorByName("translations][$langcode][$key", $this->t('The submitted string contains disallowed HTML: %string', ['%string' => $value]));
            $this->logger('locale')->warning('Attempted submission of a translation string with disallowed HTML: %string', ['%string' => $value]);
          }
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $form_langcode = $form_state->getValue('langcode');
    $strings = $form_state->getValue('strings', []);
    if (!is_array($strings) || empty($strings)) {
      return;
    }

    // Preload all translations for strings in the form.
    $lids = array_keys($strings);
    $existing_translation_objects = [];
    $conditions = ['lid' => $lids, 'translated' => TRUE];
    if (!empty($form_langcode) && $form_langcode !== 'all') {
      $conditions['language'] = $form_langcode;
    }
    foreach ($this->localeStorage->getTranslations($conditions) as $existing_translation_object) {
      $existing_translation_objects[$existing_translation_object->lid][$existing_translation_object->language] = $existing_translation_object;
    }

    $updated_by_langcode = [];

    foreach ($strings as $lid => $values) {
      if (!is_array($values)) {
        continue;
      }
      foreach ($values as $langcode => $new_translation) {
        if ($langcode === 'original' || !is_array($new_translation)) {
          continue;
        }

        $existing_translation = isset($existing_translation_objects[$lid][$langcode]);
        $existing_translation_object = $existing_translation ? $existing_translation_objects[$lid][$langcode] : NULL;

        // Plural translations are saved in a delimited string. To be able
        // to compare the new strings with the existing strings a string in
        // the same format is created.
        $new_translation_string_delimited = implode(PoItem::DELIMITER, $new_translation);

        // Generate an imploded string without delimiter, to be able to run
        // empty() on it.
        $new_translation_string = implode('', $new_translation);

        $is_changed = FALSE;

        if ($existing_translation && $existing_translation_object->translation != $new_translation_string_delimited) {
          // If there is an existing translation in the DB and the new
          // translation is not the same as the existing one.
          $is_changed = TRUE;
        }
        elseif (!$existing_translation && !empty($new_translation_string)) {
          // Newly entered translation.
          $is_changed = TRUE;
        }

        if ($is_changed) {
          // Only update or insert if we have a value to use.
          $target = $existing_translation_object
            ?? $this->localeStorage->createTranslation(['lid' => $lid, 'language' => $langcode]);
          $target->setPlurals($new_translation)
            ->setCustomized()
            ->save();
          $updated_by_langcode[$langcode][] = $target->getId();
        }
        if (empty($new_translation_string) && $existing_translation) {
          // Empty new translation entered: remove existing entry from database.
          $existing_translation_object->delete();
          $updated_by_langcode[$langcode][] = $lid;
        }
      }
    }

    $this->messenger()->addStatus($this->t('The strings have been saved.'));

    // Keep the user on the current pager page.
    $page = $this->getRequest()->query->get('page');
    if (isset($page)) {
      $form_state->setRedirect(
        'locale.translate_page',
        [],
        ['page' => $page]
      );
    }

    foreach ($updated_by_langcode as $langcode => $updated) {
      if (!empty($updated)) {
        // Clear cache and force refresh of JavaScript translations.
        _locale_refresh_translations([$langcode], $updated);
        _locale_refresh_configuration([$langcode], $updated);
      }
    }
  }

}
