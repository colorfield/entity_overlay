<?php

namespace Drupal\entity_overlay\Ajax;

use Drupal\Core\Ajax\CommandInterface;
use Drupal\Core\Entity\EntityInterface;

/**
 * Entity overlay command.
 */
class EntityOverlayCommand implements CommandInterface {

  /**
   * Drupal\Core\Entity\EntityInterface definition.
   *
   * @var EntityInterface
   */
  protected $entity;

  /**
   * Rendered array of a view mode.
   *
   * @var array
   */
  protected $view;

  /**
   * Constructor.
   *
   * @todo extend ReplaceCommand
   */
  public function __construct(EntityInterface $entity, array $view) {
    $this->entity = $entity;
    $this->view = $view;
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    return array(
      'command' => 'entityOverlay',
      // @todo cleaning up
      'entity_type_id' => $this->entity->getEntityTypeId(),
      'entity_id' => $this->entity->id(),
      'entity' => $this->entity,
      'view' => $this->view,
      // @todo use getRenderedContent
      'rendered_entity' => \Drupal::service('renderer')->renderRoot($this->view),
    );
  }

}
