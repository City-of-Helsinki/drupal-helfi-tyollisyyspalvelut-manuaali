<?php

namespace Drupal\hel_tpm_search\Plugin\better_exposed_filters\filter;

use Drupal\Core\Entity\TranslatableInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\search_api\Plugin\views\filter\SearchApiFilterTrait;
use Drupal\selective_better_exposed_filters\Plugin\better_exposed_filters\filter\SelectiveFilterBase;
use Drupal\views\Plugin\views\filter\Bundle;
use Drupal\views\Plugin\views\filter\FilterPluginBase;
use Drupal\views\ViewExecutable;
use Drupal\views\Views;

/**
 * Base class for selective service language.
 */
abstract class SelectiveLanguageBase extends SelectiveFilterBase {

  /**
   * {@inheritdoc}
   */
  public static function exposedFormAlter(ViewExecutable &$current_view, FilterPluginBase $filter, array $settings, array &$form, FormStateInterface &$form_state) {
    if (!$filter->isExposed() || empty($settings['options_show_only_used'])) {
      return;
    }

    $identifier = $filter->options['is_grouped'] ? $filter->options['group_info']['identifier'] : $filter->options['expose']['identifier'];

    // If request not from this function.
    if (empty($current_view->selective_filter)) {
      /** @var \Drupal\views\ViewExecutable $view */
      $view = Views::getView($current_view->id());
      $view->selective_filter = TRUE;
      $view->setArguments($current_view->args);
      $view->setDisplay($current_view->current_display);
      $view->preExecute();

      if (!empty($view->display_handler->getPlugin('exposed_form')->options['bef']['general']['input_required'])) {
        $view->display_handler->getPlugin('exposed_form')->options['bef']['general']['input_required'] = FALSE;
      }

      // Include all results of a view, ignoring items_per_page that
      // are set in view itself or in one of `views_pre_view` hooks,
      // which are executed in `$view->preExecute()`.
      $view->setItemsPerPage(0);

      // Query parameters can override default results.
      // Save original query and replace with one without parameters.
      $query_param = &$view->getRequest()->query;
      $query_param_orig = clone $query_param;
      // Disable per page param.
      $query_param->remove('items_per_page');

      // Unset exposed filters values.
      if (!empty($settings['options_show_only_used_filtered'])) {
        // Unset current filter value from input to avoid only one option,
        // in other way current filter value will restrict himself.
        if ($query_param->has($identifier)) {
          $query_param->remove($identifier);
        }
      }
      else {
        // In this case we need to skip all filled values for full result.
        foreach ($query_param->keys() as $key) {
          $query_param->remove($key);
        }
      }

      // Execute modified query.
      $view->execute();

      // Restore parameters for main query.
      $view->getRequest()->query = $query_param_orig;

      $element = &$form[$identifier];
      if (!empty($view->result)) {
        $relationship = $filter->options['relationship'];

        if (in_array(SearchApiFilterTrait::class, class_uses($filter)) || $filter instanceof Bundle) {
          $field_id = $filter->options['field'];

          // For Search API fields find original property path:
          if (in_array(SearchApiFilterTrait::class, class_uses($filter))) {
            $index_fields = $view->getQuery()->getIndex()->getFields();
            if (isset($index_fields[$field_id])) {
              $field_id = $index_fields[$field_id]->getPropertyPath();
            }
          }
        }
        else {
          $field_id = $filter->definition['field_name'];
        }

        $ids = [];
        $relationship_count = [];

        // Avoid illegal choice.
        $user_value = $form_state->getUserInput()[$identifier] ?? NULL;
        if (isset($user_value)) {
          if (is_array($user_value)) {
            $ids = $user_value;
          }
          else {
            $ids[$user_value] = $user_value;
          }
        }

        $row_revisions = [];

        foreach ($view->result as $row) {
          $entity = $row->_entity;
          if ($relationship != 'none') {
            $entity = $row->_relationship_entities[$relationship] ?? FALSE;
          }
          // Get entity from object.
          if (!isset($entity)) {
            $entity = $row->_object->getEntity();
          }
          if ($entity instanceof TranslatableInterface
            && isset($row->node_field_data_langcode)
            && $entity->hasTranslation($row->node_field_data_langcode)) {
            $entity = $entity->getTranslation($row->node_field_data_langcode);
          }

          $row_revisions[] = $entity->getRevisionId();
        }

        $relationship_count = self::getItemValues($field_id, $row_revisions);
        foreach ($relationship_count as $key => $count) {
          $ids[$key] = $key;
        }

        if (!empty($element['#options'])) {
          foreach ($element['#options'] as $key => $option) {
            if ($key === 'All') {
              continue;
            }

            $target_id = $key;
            if (is_object($option) && !empty($option->option)) {
              $target_id = array_keys($option->option);
              $target_id = reset($target_id);
            }
            if (!in_array($target_id, $ids)) {
              unset($element['#options'][$key]);
            }
            elseif (!empty($settings['options_show_items_count'])) {
              $count = $relationship_count[$target_id] ?? 0;
              $element['#options'][$key] = $element['#options'][$key] . ' (' . $count . ')';
            }
          }
          // Make the element size fit with the new number of options.
          if (isset($element['#size'])) {
            if (count($element['#options']) >= 2 && count($element['#options']) < $element['#size']) {
              $element['#size'] = count($element['#options']);
            }
          }

          if (
            !empty($settings['options_hide_when_empty'])
            && (
              (count($element['#options']) == 1 && isset($element['#options']['All']))
              || empty($element['#options'])
            )
          ) {
            $element['#access'] = FALSE;
          }
        }
      }
      elseif (!empty($settings['options_hide_when_empty'])) {
        $element['#access'] = FALSE;
      }
    }
  }

  /**
   * Get item values for service language field.
   *
   * @param string $field_id
   *   Field path.
   * @param array $entity_revisions
   *   Array of entity revisions.
   *
   * @return array
   *   Array of language tids.
   */
  protected static function getItemValues($field_id, array $entity_revisions) {
    // Currently this plugin is used for specific use case and works only for
    // Entity -> paragraph references due to performance issues. This might need
    // refactoring at some point.
    $field_ids = explode(':', $field_id);
    $first_field = $field_ids[0];
    $last_field = end($field_ids);

    $paragraphs = \Drupal::database()->select('node__' . $first_field, 'n')
      ->fields('n', [$first_field . '_target_revision_id'])
      ->condition('revision_id', $entity_revisions, 'IN')
      ->execute()->fetchAllAssoc($first_field . '_target_revision_id');
    $refs = array_keys($paragraphs);

    $table = 'paragraph__' . $last_field;
    $col = $last_field . '_target_id';
    $result = \Drupal::database()->select($table, 'pfs')
      ->fields('pfs', [$col])
      ->condition('revision_id', $refs, 'IN')
      ->execute()->fetchAll();
    $values = [];

    foreach ($result as $val) {
      $key = $val->{$col};
      $values[$key] += 1;
    }

    return $values;
  }

}
