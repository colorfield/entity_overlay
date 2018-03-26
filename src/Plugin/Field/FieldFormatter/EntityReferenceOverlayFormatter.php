<?php

namespace Drupal\entity_overlay\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\Plugin\Field\FieldFormatter\EntityReferenceFormatterBase;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'entity reference overlay' formatter.
 *
 * @FieldFormatter(
 *   id = "entity_reference_entity_overlay_formatter",
 *   label = @Translation("Rendered entity overlay"),
 *   description = @Translation("Display a view mode of the referenced entities and display another view mode of the rendered entity on click as an overlay."),
 *   field_types = {
 *     "entity_reference"
 *   }
 * )
 */
class EntityReferenceOverlayFormatter extends EntityReferenceFormatterBase implements ContainerFactoryPluginInterface {

  use EntityOverlayFormatterBase;

  /**
   * The number of times this formatter allows rendering the same entity.
   *
   * @var int
   */
  const RECURSIVE_RENDER_LIMIT = 20;

  /**
   * The logger factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface
   */
  protected $entityDisplayRepository;

  /**
   * An array of counters for the recursive rendering protection.
   *
   * Each counter takes into account all the relevant information about the
   * field and the referenced entity that is being rendered.
   *
   * @var array
   */
  protected static $recursiveRenderDepth = [];

  /**
   * Constructs a EntityReferenceEntityFormatter instance.
   *
   * @param string $plugin_id
   *   The plugin_id for the formatter.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The definition of the field to which the formatter is associated.
   * @param array $settings
   *   The formatter settings.
   * @param string $label
   *   The formatter label display setting.
   * @param string $view_mode
   *   The view mode.
   * @param array $third_party_settings
   *   Any third party settings settings.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityDisplayRepositoryInterface $entity_display_repository
   *   The entity display repository.
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, $label, $view_mode, array $third_party_settings, LoggerChannelFactoryInterface $logger_factory, EntityTypeManagerInterface $entity_type_manager, EntityDisplayRepositoryInterface $entity_display_repository) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings);
    $this->loggerFactory = $logger_factory;
    $this->entityTypeManager = $entity_type_manager;
    $this->entityDisplayRepository = $entity_display_repository;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['label'],
      $configuration['view_mode'],
      $configuration['third_party_settings'],
      $container->get('logger.factory'),
      $container->get('entity_type.manager'),
      $container->get('entity_display.repository')
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'list_view_mode' => 'teaser',
      'overlay_view_mode' => 'default',
      'link' => FALSE,
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    // @todo add settings: width, height, show 'open' link, library
    $elements['list_view_mode'] = [
      '#type' => 'select',
      '#options' => $this->entityDisplayRepository->getViewModeOptions($this->getFieldSetting('target_type')),
      '#title' => t('List view mode'),
      '#default_value' => $this->getSetting('list_view_mode'),
      '#required' => TRUE,
    ];
    $elements['overlay_view_mode'] = [
      '#type' => 'select',
      '#options' => $this->entityDisplayRepository->getViewModeOptions($this->getFieldSetting('target_type')),
      '#title' => t('Overlay view mode'),
      '#default_value' => $this->getSetting('overlay_view_mode'),
      '#required' => TRUE,
    ];

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = [];

    $view_modes = $this->entityDisplayRepository->getViewModeOptions($this->getFieldSetting('target_type'));
    $list_view_mode = $this->getSetting('list_view_mode');
    $overlay_view_mode = $this->getSetting('overlay_view_mode');
    $summary[] = t('List rendered as @mode', ['@mode' => isset($view_modes[$list_view_mode]) ? $view_modes[$list_view_mode] : $list_view_mode]);
    $summary[] = t('Overlay rendered as @mode', ['@mode' => isset($view_modes[$overlay_view_mode]) ? $view_modes[$overlay_view_mode] : $overlay_view_mode]);

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];
    $list_view_mode = $this->getSetting('list_view_mode');
    $overlay_view_mode = $this->getSetting('overlay_view_mode');

    // Prepare settings that will be passed to javascript behaviours.
    $entitySettings = [];
    foreach ($this->getEntitiesToView($items, $langcode) as $delta => $entity) {
      if (!$entity->isNew()) {
        $view_builder = $this->entityTypeManager->getViewBuilder($entity->getEntityTypeId());
        $elements[$delta] = [
          '#theme' => 'entity_overlay_list_item',
          '#entity_view' => $view_builder->view($entity, $list_view_mode, $entity->language()->getId()),
          '#entity_id' => $entity->id(),
          '#entity_type_id' => $entity->getEntityTypeId(),
          '#entity_overlay_link' => $this->getOverlayLink($entity, $overlay_view_mode),
        ];

        // @todo review path structure for each content entity type
        $pathMatch = [$entity->getEntityTypeId() . '/' . $entity->id()];
        // @todo add path aliases
        $entitySettings[$entity->getEntityTypeId() . '_' . $entity->id()] = [
          'overlay_url' => $this->getOverlayUrl($entity, $overlay_view_mode)->toString(),
          'path_match' => $pathMatch,
        ];

        if (!empty($items[$delta]->_attributes)) {
          $elements[$delta]['#options'] += ['attributes' => []];
          $elements[$delta]['#options']['attributes'] += $items[$delta]->_attributes;
          // Unset field item attributes since they have been included in the
          // formatter output and shouldn't be rendered in the field template.
          unset($items[$delta]->_attributes);
        }
      }
      else {
        continue;
      }
      $elements[$delta]['#cache']['tags'] = $entity->getCacheTags();
    }

    // Container for loading entity content.
    $elements[] = [
      '#type' => 'container',
      '#attributes' => [
        'id' => 'entity-overlay__container',
      ],
    ];

    $elements['#attached']['library'][] = 'core/drupal.ajax';
    $elements['#attached']['library'][] = 'entity_overlay/entity_overlay.commands';
    $elements['#attached']['library'][] = 'entity_overlay/entity_overlay.behaviors';
    $elements['#attached']['drupalSettings'] = [
      'entity_overlay' => $entitySettings,
    ];

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition) {
    // This formatter is only available for entity types that have a view
    // builder.
    $target_type = $field_definition->getFieldStorageDefinition()->getSetting('target_type');
    return \Drupal::entityManager()->getDefinition($target_type)->hasViewBuilderClass();
  }

}
