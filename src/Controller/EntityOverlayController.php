<?php

namespace Drupal\entity_overlay\Controller;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\entity_overlay\Ajax\EntityOverlayCommand;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Class EntityOverlayController.
 */
class EntityOverlayController extends ControllerBase {

  /**
   * Fetch a loaded entity for a type in a view mode.
   *
   * @param string $method
   *   Method.
   * @param string $entity_type_id
   *   Entity type.
   * @param int $entity_id
   *   Entity id.
   * @param string $view_mode
   *   View mode.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse||\Symfony\Component\HttpFoundation\Response
   *   Redirect or ajax response.
   */
  public function getEntity($method, $entity_type_id, $entity_id, $view_mode) {
    // If nojs is the method redirect the user.
    $redirect = $method === 'nojs';

    // Javascript is ok.
    if (!$redirect) {
      try {
        $entity = $this->entityTypeManager()->getStorage($entity_type_id)->load($entity_id);
        $view_builder = $this->entityTypeManager()->getViewBuilder($entity_type_id);

        // Get the render array of this entity in the specified view mode.
        $render = $view_builder->view($entity, $view_mode);

        $build = [
          '#type' => 'container',
          '#attributes' => [
            'id' => 'entity-overlay__container',
            'class' => 'entity-overlay__container--' . $entity_type_id . '-' . $entity_id,
          ],
          'entity' => $render,
        ];

        $response = new AjaxResponse();
        // $response->addCommand(new ReplaceCommand(
        // '#entity-overlay__container', $build));.
        $response->addCommand(new EntityOverlayCommand($entity, $build));
      }
      catch (InvalidPluginDefinitionException $exception) {
        print $exception->getMessage();
      }
    }
    else {
      // Javascript is not used, redirect to the entity.
      $response = new RedirectResponse(Url::fromRoute("entity.{$entity_type_id}.canonical", [$entity_type_id => $entity_id])->toString(), 302);
    }

    return $response;
  }

}
