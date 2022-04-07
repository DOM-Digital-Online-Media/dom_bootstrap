<?php

namespace Drupal\dom_bootstrap\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Breadcrumb\BreadcrumbBuilderInterface;
use Drupal\Core\Breadcrumb\BreadcrumbManager;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Menu\LocalTaskManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Routing\RouteMatchInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a block to show primary and secondary tabs as collapsible menu.
 *
 * @Block(
 *   id = "dom_bootstrap_tabs",
 *   admin_label = @Translation("Collapsible tabs"),
 *   category = @Translation("DOM bootstrap")
 * )
 */
class DomBootstrapTabs extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * Local tasks manager service.
   *
   * @var \Drupal\Core\Menu\LocalTaskManagerInterface
   */
  protected $localTaskManager;

  /**
   * Route match service.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * Breadcrumb builder service.
   *
   * @var \Drupal\Core\Breadcrumb\BreadcrumbBuilderInterface
   */
  protected $breadcrumbBuilder;

  /**
   * Creates a DomBootstrapTabs instance.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Menu\LocalTaskManagerInterface $local_task_manager
   *   Local tasks manager service.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   Route match service.
   * @param \Drupal\Core\Breadcrumb\BreadcrumbBuilderInterface $breadcrumb_builder
   *   Breadcrumb builder service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, LocalTaskManagerInterface $local_task_manager, RouteMatchInterface $route_match, BreadcrumbBuilderInterface $breadcrumb_builder) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->localTaskManager = $local_task_manager;
    $this->routeMatch = $route_match;
    $this->breadcrumbBuilder = $breadcrumb_builder;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('plugin.manager.menu.local_task'),
      $container->get('current_route_match'),
      $container->get('breadcrumb')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'primary' => TRUE,
      'secondary' => TRUE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form['primary'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show primary tabs'),
      '#default_value' => $this->configuration['primary'],
    ];
    $form['secondary'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show secondary tabs'),
      '#default_value' => $this->configuration['secondary'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $this->configuration['primary'] = $form_state->getValue('primary');
    $this->configuration['secondary'] = $form_state->getValue('secondary');
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $build = [];
    $cacheability = new CacheableMetadata();
    $cacheability->addCacheableDependency($this->localTaskManager);

    if ($this->configuration['secondary']) {
      $links = $this->localTaskManager->getLocalTasks($this->routeMatch->getRouteName(), 1);
      $cacheability = $cacheability->merge($links['cacheability']);

      $build['secondary'] = [
        '#theme' => 'collapsible_links',
        '#links' => count(Element::getVisibleChildren($links['tabs'])) > 1 ? $links['tabs'] : [],
        '#mobile_column' => 4,
      ];
    }
    if ($this->configuration['primary']) {
      $links = $this->localTaskManager->getLocalTasks($this->routeMatch->getRouteName(), 0);
      $cacheability = $cacheability->merge($links['cacheability']);

      $build['primary'] = [
        '#theme' => 'collapsible_links',
        '#links' => count(Element::getVisibleChildren($links['tabs'])) > 1 ? $links['tabs'] : [],
        '#mobile_column' => 4,
      ];

      // Get title for primary tabs from parent breadcrumb.
      $breadcrumbs = $this->breadcrumbBuilder->build($this->routeMatch)
        ->getLinks();
      if (isset($breadcrumbs[count($breadcrumbs) - 1])) {
        $title = $breadcrumbs[count($breadcrumbs) - 1]->getText();
      }
      else {
        $title = $this->t('Home');
      }
      $build['primary']['#title'] = $title;
    }

    if ($build['secondary']['#links']) {
      foreach ($build['primary']['#links'] as $item) {
        if ($item['#active']) {
          // Get title for secondary tabs from active primary tab.
          $build['secondary']['#title'] = $item['#link']['title'];
          break;
        }
      }
      $build['secondary']['#active'] = TRUE;
    }
    else {
      $build['primary']['#active'] = TRUE;
    }

    $cacheability->applyTo($build);
    return $build;
  }

}
