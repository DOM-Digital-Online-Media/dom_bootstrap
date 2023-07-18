<?php

namespace Drupal\dom_bootstrap\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Menu\MenuActiveTrailInterface;
use Drupal\Core\Menu\MenuLinkTreeInterface;
use Drupal\Core\Menu\MenuTreeParameters;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a collapsible menu block.
 *
 * @Block(
 *   id = "dom_bootstrap_collapsible_menu",
 *   admin_label = @Translation("Collapsible menu"),
 *   category = @Translation("DOM bootstrap")
 * )
 */
class CollapsibleMenuBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The menu link tree service.
   *
   * @var \Drupal\Core\Menu\MenuLinkTreeInterface
   */
  protected $menuTree;

  /**
   * The active menu trail service.
   *
   * @var \Drupal\Core\Menu\MenuActiveTrailInterface
   */
  protected $menuActiveTrail;

  /**
   * Module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * Constructs a new CollapsibleMenuBlock instance.
   *
   * @param array $configuration
   *   The plugin configuration, i.e. an array with configuration values keyed
   *   by configuration option name. The special key 'context' may be used to
   *   initialize the defined contexts by setting it to an array of context
   *   values keyed by context names.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\Core\Menu\MenuLinkTreeInterface $menu_link_tree
   *   The menu link tree service.
   * @param \Drupal\Core\Menu\MenuActiveTrailInterface $menu_active_trail
   *   The active menu trail service.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   Module handler service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, MenuLinkTreeInterface $menu_link_tree, MenuActiveTrailInterface $menu_active_trail, ModuleHandlerInterface $module_handler) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
    $this->menuTree = $menu_link_tree;
    $this->menuActiveTrail = $menu_active_trail;
    $this->moduleHandler = $module_handler;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('menu.link_tree'),
      $container->get('menu.active_trail'),
      $container->get('module_handler')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'menu_name' => '',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $menus = $this->entityTypeManager->getStorage('menu')->loadMultiple();
    $options = [];
    foreach ($menus as $menu) {
      $options[$menu->id()] = $menu->label();
    }

    $form['menu_name'] = [
      '#type' => 'select',
      '#options' => $options,
      '#title' => $this->t('Menu name'),
      '#description' => $this->t('The menu will be shown up to second level due to bootstrap collapsible limitations.'),
      '#default_value' => $this->configuration['menu_name'],
      '#required' => TRUE,
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $this->configuration['menu_name'] = $form_state->getValue('menu_name');
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $parameters = new MenuTreeParameters();
    $active_trail = $this->menuActiveTrail
      ->getActiveTrailIds($this->configuration['menu_name']);
    $parameters->setActiveTrail($active_trail);
    $parameters->setMaxDepth(2);

    $tree = $this->menuTree
      ->load($this->configuration['menu_name'], $parameters);
    $manipulators = [
      ['callable' => 'menu.default_tree_manipulators:checkAccess'],
      ['callable' => 'menu.default_tree_manipulators:generateIndexAndSort'],
    ];
    $tree = $this->menuTree->transform($tree, $manipulators);
    $original_build = $this->menuTree->build($tree);

    $this->moduleHandler->alter('dom_bootstrap_collapsible_menu', $original_build);

    $build = [
      '#cache' => $original_build['#cache'],
    ];
    foreach ($original_build['#items'] as $name => $item) {
      $build[$name] = [
        '#theme' => 'collapsible_links',
        '#title' => $item['title'],
        '#title_url' => $item['url']->toString(),
        '#title_attributes' => $item['attributes']->toArray(),
        '#active' => $item['in_active_trail'],
        '#collapsed' => !($item['in_active_trail'] || $item['original_link']->isExpanded()),
      ];
      if (!empty($item['below'])) {
        unset($build[$name]['#title_url']);
        foreach ($item['below'] as $child_name => $child) {
          if (isset($child['put_delimiter'])) {
            $build[$name]['#links'][$child_name] = [
              '#type' => 'html_tag',
              '#tag' => 'hr',
            ];
          }
          else {
            $build[$name]['#links'][$child_name] = Link::fromTextAndUrl($child['title'], $child['url'])->toRenderable();
            $build[$name]['#links'][$child_name]['#attributes'] = $child['attributes']->toArray();
          }
        }
      }
      else {
        // That way is-active link will be set properly to first level items.
        // @see \Drupal\Core\Utility\LinkGenerator::generate()
        $system_path = $item['url']->getInternalPath();
        if ($item['url']->getRouteName() === '<front>') {
          $system_path = '<front>';
        }
        $build[$name]['#title_attributes']['data-drupal-link-system-path'] = $system_path;
        $build[$name]['#icons'] = [];
      }
    }

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    $cache_tags = parent::getCacheTags();
    $cache_tags[] = 'config:system.menu.' . $this->configuration['menu_name'];
    return $cache_tags;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    return Cache::mergeContexts(parent::getCacheContexts(), [
      'route.menu_active_trails:' . $this->configuration['menu_name']
    ]);
  }

}
