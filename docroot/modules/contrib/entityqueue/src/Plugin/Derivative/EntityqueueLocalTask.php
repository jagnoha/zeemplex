<?php

/**
 * @file
 * Contains \Drupal\entityqueue\Plugin\Derivative\EntityqueueLocalTask.
 */

namespace Drupal\entityqueue\Plugin\Derivative;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides local task definitions for all entity bundles.
 */
class EntityqueueLocalTask extends DeriverBase implements ContainerDeriverInterface {

  use StringTranslationTrait;

  /**
   * The entity manager
   *
   * @var \Drupal\Core\Entity\EntityManagerInterface
   */
  protected $entityManager;

  /**
   * Creates an EntityqueueLocalTask object.
   *
   * @param \Drupal\Core\Entity\EntityManagerInterface $entity_manager
   *   The entity manager.
   * @param \Drupal\Core\StringTranslation\TranslationInterface $string_translation
   *   The translation manager.
   */
  public function __construct(EntityManagerInterface $entity_manager, TranslationInterface $string_translation) {
    $this->entityManager = $entity_manager;
    $this->stringTranslation = $string_translation;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id) {
    return new static(
      $container->get('entity.manager'),
      $container->get('string_translation')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    $this->derivatives = array();

    foreach ($this->entityManager->getDefinitions() as $entity_type_id => $entity_type) {
      if ($entity_type->hasViewBuilderClass() && $entity_type->hasLinkTemplate('canonical')) {
        $entityqueue_route_name = "entity.$entity_type_id.entityqueue";
        $this->derivatives[$entityqueue_route_name] = array(
          'entity_type' => $entity_type_id,
          'title' => $this->t('Entityqueue'),
          'route_name' => $entityqueue_route_name,
          'base_route' => "entity.$entity_type_id.canonical",
          'weight' => 21, // move after edit, delete, revisions ... etc tabs.
        ) + $base_plugin_definition;
      }
    }

    return parent::getDerivativeDefinitions($base_plugin_definition);
  }

}
