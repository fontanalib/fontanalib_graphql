<?php

namespace Drupal\fontanalib_graphql\Plugin\GraphQL\DataProducer;

use Drupal\Core\Entity\EntityInterface;
use Drupal\graphql\Plugin\GraphQL\DataProducer\DataProducerPluginBase;

/**
 * @DataProducer(
 *   id = "entity_alias",
 *   name = @Translation("Entity identifier"),
 *   description = @Translation("Returns the entity path alias."),
 *   produces = @ContextDefinition("string",
 *     label = @Translation("Path alias")
 *   ),
 *   consumes = {
 *     "entity" = @ContextDefinition("entity",
 *       label = @Translation("Entity")
 *     )
 *   }
 * )
 */
class EntityAlias extends DataProducerPluginBase {

  /**
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *
   * @return string
   */
  public function resolve(EntityInterface $entity) {
    $nid = $entity->id();
    return \Drupal::service('path.alias_manager')->getAliasByPath('/node/'.$nid);
  }

}
