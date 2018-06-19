<?php

namespace Drupal\entityqueue\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\entityqueue\EntityQueueInterface;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Ajax\RedirectCommand;
use Drupal\entityqueue\Entity\EntityQueue;
use Drupal\entityqueue\Entity\EntitySubqueue;
use Drupal\entityqueue\EntitySubqueueInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Url;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Access\AccessResult;

/**
 * Returns responses for Views UI routes.
 */
class EntityQueueUIController extends ControllerBase {

  /**
   * Provides a list of all the subqueues of an entity queue.
   *
   * @param \Drupal\entityqueue\EntityQueueInterface $entity_queue
   *   The entity queue.
   *
   * @return array
   *   A render array.
   */
  public function subqueueList(EntityQueueInterface $entity_queue) {
    $list_builder = $this->entityTypeManager()->getListBuilder('entity_subqueue');
    $list_builder->setQueueId($entity_queue->id());

    return $list_builder->render();
  }

  /**
   * Builds the entity add to subqueues page.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match.
   * @param string $entity_type_id
   *   (optional) The entity type ID.
   *
   * @return array
   *   Array of page elements to render.
   */
  public function entitySubqueueList(RouteMatchInterface $route_match, $entity_type_id = NULL) {
    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $entity = $route_match->getParameter($entity_type_id);
  
    return $this->entityGetAllowedSubqueList($entity);
  }

  /**
   * Get entity list for subqueues allowed for this entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface
   *   The entity.
   *
   * @return array
   *   Array of page elements to render.
   */
  public function entityGetAllowedSubqueList($entity) {
    $subqueues = EntitySubqueue::loadMultiple();
    $list_builder = $this->entityTypeManager()->getListBuilder('entity_queue');

    $build['#title'] = $this->t('Entityqueues for %title', ['%title' => $entity->label()]);
    $build['#type'] = 'container';
    $build['#attributes']['id'] = 'entity-queue-list';
    $build['#attached']['library'][] = 'core/drupal.ajax';
    $build['table'] = array(
      '#type' => 'table',
      '#attributes' => array(
        'class' => array('entity-queue-listing-table'),
      ),
      '#header' => $list_builder->buildHeader(),
      '#rows' => array(),
      '#cache' => [],
    );
  
    foreach ($subqueues as $subqueue) {
      $queue = $subqueue->getQueue();
      $queue_settings = $queue->getEntitySettings();
      $target_bundles = !empty($queue_settings['handler_settings']['target_bundles']) ? $queue_settings['handler_settings']['target_bundles'] : [];
      if ($queue_settings['target_type'] == $entity->getEntityTypeId() && (empty($target_bundles) || in_array($entity->bundle(), $target_bundles))) {
        $row = $list_builder->buildRow($queue);
        // Check if entity is in queue
        $subqueue_items = $subqueue->get('items')->getValue();
        if(in_array($entity->id(), array_column($subqueue_items, 'target_id'))) {
          $url = Url::fromRoute('entity.entity_subqueue.remove_item', ['entity_queue' => $queue->id(), 'entity_subqueue' => $subqueue->id(), 'entity' => $entity->id()]);
          $row['data']['operations']['data']['#links'] = [
            'remove-item' => [
              'title' => $this->t('Remove from queue'),
              'url' => $url,
              'attributes' => [
                'class' => ['use-ajax'],
              ],
            ],
          ];
        }
        else {
          $url = Url::fromRoute('entity.entity_subqueue.add_item', ['entity_queue' => $queue->id(), 'entity_subqueue' => $subqueue->id(), 'entity' => $entity->id()]);
          $row['data']['operations']['data']['#links'] = [
            'add-item' => [
              'title' => $this->t('Add to queue'),
              'url' => $url,
              'attributes' => [
                'class' => ['use-ajax'],
              ],
            ],
          ];
        }
        $build['table']['#rows'][$queue->id()] = $row;
      }
    }
    $build['table']['#empty'] = $this->t('There are no available queues.');
  
    return $build;
  }

  /**
   * Returns a form to add a new subqeue.
   *
   * @param \Drupal\entityqueue\EntityQueueInterface $entity_queue
   *   The queue this subqueue will be added to.
   *
   * @return array
   *   The entity subqueue add form.
   */
  public function addForm(EntityQueueInterface $entity_queue) {
    $subqueue = $this->entityTypeManager()->getStorage('entity_subqueue')->create(['queue' => $entity_queue->id()]);
    return $this->entityFormBuilder()->getForm($subqueue);
  }

  /**
   * Calls a method on an entity queue and reloads the listing page.
   *
   * @param \Drupal\entityqueue\EntityQueueInterface $entity_queue
   *   The view being acted upon.
   * @param string $op
   *   The operation to perform, e.g., 'enable' or 'disable'.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse|\Symfony\Component\HttpFoundation\RedirectResponse
   *   Either returns a rebuilt listing page as an AJAX response, or redirects
   *   back to the listing page.
   */
  public function ajaxOperation(EntityQueueInterface $entity_queue, $op, Request $request) {
    // Perform the operation.
    $entity_queue->$op()->save();

    // If the request is via AJAX, return the rendered list as JSON.
    if ($request->request->get('js')) {
      $list = $this->entityTypeManager()->getListBuilder('entity_queue')->render();
      $response = new AjaxResponse();
      $response->addCommand(new ReplaceCommand('#entity-queue-list', $list));
      return $response;
    }

    // Otherwise, redirect back to the page.
    return $this->redirect('entity.entity_queue.collection');
  }

  /**
   * Calls a method on an entity subqueue page and reloads the page.
   *
   * @param \Drupal\entityqueue\EntitySubqueueInterface $entity_subqueue
   *   The view being acted upon.
   * @param string $op
   *   The operation to perform, e.g., 'add-item' or 'remove-item'.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse|\Symfony\Component\HttpFoundation\RedirectResponse
   *   Either returns a rebuilt listing page as an AJAX response, or redirects
   *   back to the subqueue page.
   */
  public function subqueueAjaxOperation(EntitySubqueueInterface $entity_subqueue, $op, Request $request) {
    $entity_id = $request->get('entity');
    $entity = \Drupal::entityTypeManager()->getStorage($entity_subqueue->getQueue()->getTargetEntityTypeId())->load($entity_id);
    // Perform the operation.
    $entity_subqueue->$op($entity_id)->save();
    // If the request is via AJAX, return the rendered list as JSON.
    if ($request->request->get('js')) {
      $list = $this->entityGetAllowedSubqueList($entity);
      $response = new AjaxResponse();
      $response->addCommand(new ReplaceCommand('#entity-queue-list', $list));
      return $response;
    }
    else {
      $response = new AjaxResponse();
      $response->addCommand(new RedirectCommand(''));
      return $response;
    }
  }

  /**
   * Checks access for a specific request.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match.
   * @param string $entity_type_id
   *   (optional) The entity type ID.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(RouteMatchInterface $route_match, $entity_type_id = NULL) {
    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $entity = $route_match->getParameter($entity_type_id);
    $subqueues = EntitySubqueue::loadMultiple();
    if (isset($subqueues) && count($subqueues) > 0) {
      foreach ($subqueues as $subqueue) {
        $queue = $subqueue->getQueue();
        if (isset($queue)) {
          $queue_settings = $queue->getEntitySettings();
          $target_bundles = !empty($queue_settings['handler_settings']['target_bundles']) ? $queue_settings['handler_settings']['target_bundles'] : [];
          if ($queue_settings['target_type'] == $entity_type_id && (empty($target_bundles) || in_array($entity->bundle(), $target_bundles))) {
            return AccessResult::allowed();
          }
        }
      }
    }

    return AccessResult::forbidden();
  }
}
