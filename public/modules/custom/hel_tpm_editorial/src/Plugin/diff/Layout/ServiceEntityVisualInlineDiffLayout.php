<?php

namespace Drupal\hel_tpm_editorial\Plugin\diff\Layout;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Theme\ThemeInitializationInterface;
use Drupal\Core\Theme\ThemeManagerInterface;
use Drupal\diff\DiffEntityComparison;
use Drupal\diff\DiffEntityParser;
use Drupal\entity_diff_ui\Plugin\diff\Layout\EntityVisualInlineDiffLayout;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Provides Service entity inline diff layout.
 *
 * @DiffLayoutBuilder(
 *   id = "service_entity_visual_inline",
 *   label = @Translation("Service Visual Inline"),
 *   description = @Translation("Custom Visual layout, displays revision comparison using the entity type view mode."),
 * )
 */
class ServiceEntityVisualInlineDiffLayout extends EntityVisualInlineDiffLayout {

  /**
   * Theme manager.
   *
   * @var \Drupal\Core\Theme\ThemeManagerInterface
   */
  private ThemeManagerInterface $themeManager;

  /**
   * Theme initiliazation service.
   *
   * @var \Drupal\Core\Theme\ThemeInitializationInterface
   */
  private ThemeInitializationInterface $themeInitialization;

  public function __construct(array $configuration, $plugin_id, $plugin_definition, ConfigFactoryInterface $config, EntityTypeManagerInterface $entity_type_manager, DiffEntityParser $entity_parser, DateFormatterInterface $date, RendererInterface $renderer, DiffEntityComparison $entity_comparison, \HtmlDiffAdvancedInterface $html_diff, RequestStack $request_stack, EntityDisplayRepositoryInterface $entity_display_repository, ThemeManagerInterface $theme_manager, ThemeInitializationInterface $theme_initialization) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $config, $entity_type_manager, $entity_parser, $date, $renderer, $entity_comparison, $html_diff, $request_stack, $entity_display_repository);
    $this->themeManager = $theme_manager;
    $this->themeInitialization = $theme_initialization;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('config.factory'),
      $container->get('entity_type.manager'),
      $container->get('diff.entity_parser'),
      $container->get('date.formatter'),
      $container->get('renderer'),
      $container->get('diff.entity_comparison'),
      $container->get('diff.html_diff'),
      $container->get('request_stack'),
      $container->get('entity_display.repository'),
      $container->get('theme.manager'),
      $container->get('theme.initialization')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build(ContentEntityInterface $left_revision, ContentEntityInterface $right_revision, ContentEntityInterface $entity) {
    // Build the revisions data.
    $build = $this->buildRevisionsData($left_revision, $right_revision);

    $this->entityTypeManager->getStorage($entity->getEntityTypeId())->resetCache([$entity->id()]);

    $active_view_mode = 'default';

    $this->switchTheme('admin');

    $view_builder = $this->entityTypeManager->getViewBuilder($entity->getEntityTypeId());

    // Trigger exclusion of interactive items like on preview.
    $left_revision->in_preview = TRUE;
    $right_revision->in_preview = TRUE;
    $left_view = $view_builder->view($left_revision, $active_view_mode);
    $right_view = $view_builder->view($right_revision, $active_view_mode);

    // Avoid render cache from being built.
    unset($left_view['#cache']);
    unset($right_view['#cache']);

    $html_1 = $this->renderer->render($left_view);
    $html_2 = $this->renderer->render($right_view);

    $this->htmlDiff->setOldHtml($html_1);
    $this->htmlDiff->setNewHtml($html_2);
    $this->htmlDiff->build();

    $this->switchTheme();

    $build['diff'] = [
      '#markup' => $this->htmlDiff->getDifference(),
      '#weight' => 10,
    ];

    $build['#attached']['library'][] = 'diff/diff.visual_inline';
    return $build;
  }

  /**
   * Switches the active theme to the specified theme.
   *
   * @param string $theme
   *   The machine name of the theme to switch to. Defaults to 'default'.
   *
   * @return void
   *   Does not return a value.
   */
  protected function switchTheme($theme = 'default') {
    $selected_theme = $this->configFactory->get('system.theme')->get($theme); {
    if ($selected_theme) {
      $this->themeManager->setActiveTheme($this->themeInitialization->initTheme($selected_theme));
    }
    }
  }

}
