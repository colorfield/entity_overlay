<?php

namespace Drupal\entity_overlay\Controller;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Url;
use Drupal\entity_overlay\Ajax\EntityOverlayCommand;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\entity_tools\EntityTools;
use Symfony\Component\HttpFoundation\Response;
use Zend\Diactoros\Response\RedirectResponse;

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
   * Fetch a loaded entity for a type in a view mode.
   *
   * @param string $method
   *   Method.
   * @param string $entity_type
   *   Entity type.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   Entity.
   * @param string $view_mode
   *   View mode.
   *
   * @return Drupal\Core\Ajax\AjaxResponse|Zend\Diactoros\Response\RedirectResponse
   *   Redirect or ajax response.
   */
  public function getEntity($method, $entity_type, EntityInterface $entity, $view_mode) {
    // If nojs is the method redirect the user.
    $redirect = $method === 'nojs';

    // Javascript is ok.
    if (!$redirect) {
      $view_builder = $this->entityTypeManager()->getViewBuilder($entity_type);

      // Get the render array of this entity in the specified view mode.
      $render = $view_builder->view($entity, $view_mode);

      // To workaround the issue where the ReplaceCommand
      // actually REMOVES the HTML element
      // selected by the selector given to the ReplaceCommand,
      // we need to wrap our content
      // in a div that same ID, otherwise only the first click
      // will work. (Since the ID will no longer exist on the page)
      $build = [
        '#type' => 'container',
        '#attributes' => [
          'id' => 'entity-overlay__container',
          'class' => 'entity-overlay__container--' . $entity_type . '-' . $entity->id(),
        ],
        'entity' => $render,
      ];

      // Now we return an AjaxResponse with the ReplaceCommand
      // to place the entity on the page.
      $response = new AjaxResponse();
      // $response->addCommand(new ReplaceCommand(
      // '#entity-overlay__container', $build));.
      $response->addCommand(new EntityOverlayCommand($entity, $build));
    }
    else {
      // @todo check response.
      // Javascript is not used, redirect to the entity.
      $response = new RedirectResponse(Url::fromRoute("entity.{$entity_type}.canonical", ["{$entity_type}" => $entity->id()]), 302);
    }

    return $response;
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
