<?php

namespace Drupal\entity_overlay\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\entity_tools\EntityTools;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class EntityOverlayController.
 */
class EntityOverlayController extends ControllerBase {

  /**
   * Drupal\entity_tools\EntityTools definition.
   *
   * @var \Drupal\entity_tools\EntityTools
   */
  protected $entityTools;

  /**
   * Constructs a new EntityOverlayController object.
   */
  public function __construct(EntityTools $entity_tools) {
    $this->entityTools = $entity_tools;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_tools')
    );
  }

  /**
   * Wrap a rendered entity in a Response.
   *
   * @todo review CacheableResponse
   *
   * @return string
   *   Returns
   */
  public function getResponse($entity_type_id, $view_mode, $entity_id) {
    $response = new Response();

    // Load the entity with the defined view mode.
    $entity = $this->entityTools->entityLoad($entity_type_id, $entity_id);
    $entityView = $this->entityTools->entityDisplay($entity, $view_mode);

    // Then wrap it in the entity overlay template to get the close option.
    $build['node_overlay_list'] = [
      '#theme' => 'entity_overlay',
      '#entity_view' => $entityView,
    ];

    $renderedView = render($build);

    $response->setContent($renderedView);
    $response->setStatusCode(Response::HTTP_OK);
    $response->headers->set('Content-Type', 'text/html');
    return $response;
  }

}
