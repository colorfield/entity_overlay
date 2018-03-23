<?php

namespace Drupal\entity_overlay\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Url;
use Drupal\entity_tools\NodeQuery;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\entity_tools\EntityTools;

/**
 * Provides a 'NodesBlock' block.
 *
 * @Block(
 *  id = "entity_overlay_nodes_block",
 *  admin_label = @Translation("Nodes overlay"),
 * )
 */
class NodesBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * Drupal\entity_tools\EntityTools definition.
   *
   * @var \Drupal\entity_tools\EntityTools
   */
  protected $entityTools;

  /**
   * Constructs a new NodesBlock object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param string $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\entity_tools\EntityTools $entity_tools
   *   Entity Tools definition.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    EntityTools $entity_tools
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTools = $entity_tools;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_tools')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'items' => 3,
      'content_type' => NULL,
      'list_view_mode' => 'teaser',
      'overlay_view_mode' => 'full',
      'list_type' => 'ul',
      'wrapper_class' => '',
      'list_class' => 'entity_overlay',
      'item_class' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $contentTypes = $this->entityTools->getContentTypes();
    $contentTypeOptions = [];
    foreach ($contentTypes as $type) {
      $contentTypeOptions[$type->id()] = $type->label();
    }

    // @todo dependency injection
    /** @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface $entityDisplayRepository */
    $entityDisplayRepository = \Drupal::service('entity_display.repository');

    $form['items'] = [
      '#type' => 'number',
      '#title' => $this->t('Items'),
      '#description' => $this->t('Amount of items to be displayed.'),
      '#default_value' => $this->configuration['items'],
    ];
    $form['content_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Content type'),
      '#description' => '',
      '#options' => $contentTypeOptions,
      '#default_value' => $this->configuration['content_type'],
      '#size' => 5,
    ];
    $form['list_view_mode'] = [
      '#type' => 'select',
      '#title' => $this->t('List view mode'),
      '#description' => '',
      '#options' => $entityDisplayRepository->getViewModeOptions('node'),
      '#default_value' => $this->configuration['list_view_mode'],
    ];
    $form['overlay_view_mode'] = [
      '#type' => 'select',
      '#title' => $this->t('Overlay view mode'),
      '#description' => '',
      '#options' => $entityDisplayRepository->getViewModeOptions('node'),
      '#default_value' => $this->configuration['overlay_view_mode'],
    ];
    $form['output'] = [
      '#type' => 'details',
      '#title' => $this->t('Output'),
      '#description' => $this->t('Change markup and classes.'),
      '#weight' => 5,
      '#open' => FALSE,
    ];
    $form['output']['list_type'] = [
      '#type' => 'radios',
      '#title' => $this->t('List type'),
      '#options' => [
        'ul' => $this->t('Unordered list'),
        'li' => $this->t('Ordered list'),
      ],
      '#default_value' => $this->configuration['list_type'],
    ];
    $form['output']['wrapper_class'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Wrapper class'),
      '#description' => $this->t('The class to provide on the wrapper, outside the list.'),
      '#default_value' => $this->configuration['wrapper_class'],
      '#maxlength' => 64,
      '#size' => 64,
    ];
    $form['output']['list_class'] = [
      '#type' => 'textfield',
      '#title' => $this->t('List class'),
      '#description' => $this->t('The class to provide on the list element itself.'),
      '#default_value' => $this->configuration['list_class'],
      '#required' => TRUE,
      '#maxlength' => 64,
      '#size' => 64,
    ];
    $form['output']['item_class'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Item class'),
      '#description' => $this->t('The class to provide on each list item.'),
      '#default_value' => $this->configuration['item_class'],
      '#maxlength' => 64,
      '#size' => 64,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $this->configuration['items'] = $form_state->getValue('items');
    $this->configuration['content_type'] = $form_state->getValue('content_type');
    $this->configuration['list_view_mode'] = $form_state->getValue('list_view_mode');
    $this->configuration['overlay_view_mode'] = $form_state->getValue('overlay_view_mode');
    $this->configuration['list_type'] = $form_state->getValue(['output', 'list_type']);
    $this->configuration['wrapper_class'] = $form_state->getValue(['output', 'wrapper_class']);
    $this->configuration['list_class'] = $form_state->getValue(['output', 'list_class']);
    $this->configuration['item_class'] = $form_state->getValue(['output', 'item_class']);
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $build = [];
    // Select and order.
    $query = new NodeQuery();
    $query->limit((int) $this->configuration['items']);
    $query->latestFirst();
    // Load from storage.
    // @todo allow selection and rendering of multiple content types
    $nodes = $this->entityTools->getNodes($this->configuration['content_type'], $query);
    // Get the display.
    $items = [];
    foreach ($nodes as $node) {
      $items[] = [
        '#theme' => 'entity_overlay_list_item',
        '#entity_view' => $this->entityTools->entityDisplay($node, $this->configuration['list_view_mode']),
        '#entity_id' => $node->id(),
      ];
    }
    // Prepare the render array.
    $listAttributes = [];
    $listAttributes['type'] = $this->configuration['list_type'];
    $listAttributes['list_class'] = $this->configuration['list_class'];
    $listAttributes['item_class'] = $this->configuration['item_class'];

    $entityList = $this->entityTools->listDisplay($items, $listAttributes);
    if (!empty($this->configuration['wrapper_class'])) {
      $build['#attributes']['class'][] = $this->configuration['wrapper_class'];
    }

    $overlayRoute = Url::fromRoute('entity_overlay.get_entity_response', [
      'entity_type_id' => 'node',
      'view_mode' => $this->configuration['overlay_view_mode'],
    // To be replaced, @todo review other ways to pass route to js.
      'entity_id' => 0,
    ]);
    $overlayPath = $overlayRoute->getInternalPath();

    $build['node_overlay_list'] = [
      '#theme' => 'entity_list',
      '#list' => $entityList,
      '#attached' => [
        'library' => [
          'entity_overlay/entity_overlay.behaviors',
        ],
        'drupalSettings' => [
          'overlay_view_mode' => $this->configuration['overlay_view_mode'],
          'list_selector' => $this->configuration['list_class'],
          'overlay_path' => $overlayPath,
        ],
      ],
    ];
    return $build;
  }

}
