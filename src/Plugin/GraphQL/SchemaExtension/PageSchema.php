<?php

namespace Drupal\fontanalib_graphql\Plugin\GraphQL\SchemaExtension;

use Drupal\graphql\GraphQL\ResolverBuilder;
use Drupal\graphql\GraphQL\ResolverRegistryInterface;
use Drupal\graphql\Plugin\GraphQL\SchemaExtension\SdlSchemaExtensionPluginBase;

/**
 * @SchemaExtension(
 *   id = "fontanalib_page",
 *   name = "Page extension",
 *   description = "A simple extension that adds node related fields for pages.",
 *   schema = "fontanalib"
 * )
 */
class PageSchema extends SdlSchemaExtensionPluginBase {
  /**
   * {@inheritdoc}
   */
  public function registerResolvers(ResolverRegistryInterface $registry) {
    $builder = new ResolverBuilder();

    $this->addQueryFields($registry, $builder);
    $this->addPageFields($registry, $builder);
  }

  /**
   * @param \Drupal\graphql\GraphQL\ResolverRegistryInterface $registry
   * @param \Drupal\graphql\GraphQL\ResolverBuilder $builder
   */
  protected function addPageFields(ResolverRegistryInterface $registry, ResolverBuilder $builder) {
    $registry->addFieldResolver('Page', 'id',
      $builder->produce('entity_id')
        ->map('entity', $builder->fromParent())
    );

    $registry->addFieldResolver('Page', 'title',
      $builder->produce('entity_label')
        ->map('entity', $builder->fromParent())
    );
    $registry->addFieldResolver('Page', 'alias',
      $builder->produce('entity_alias')
        ->map('entity', $builder->fromParent())
    );
    $registry->addFieldResolver('Page', 'body',
      $builder->compose(
        $builder->produce('property_path')
        ->map('type', $builder->fromValue("entity:node"))
        ->map('value', $builder->fromParent())
        ->map('path', $builder->fromValue("body.processed")),
        $builder->produce('text_html')
        ->map('string', $builder->fromParent()) 
      )
  );
  }

  /**
   * @param \Drupal\graphql\GraphQL\ResolverRegistryInterface $registry
   * @param \Drupal\graphql\GraphQL\ResolverBuilder $builder
   */
  protected function addQueryFields(ResolverRegistryInterface $registry, ResolverBuilder $builder) {
    $registry->addFieldResolver('Query', 'page',
      $builder->produce('entity_load')
        ->map('type', $builder->fromValue('node'))
        ->map('bundles', $builder->fromValue(['page']))
        ->map('id', $builder->fromArgument('id'))
    );
  }
}
