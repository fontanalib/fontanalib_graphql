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
  /**
   * Loads a schema definition file.
   *
   * @param string $type
   *   The type of the definition file to load.
   *
   * @return string|null
   *   The loaded definition file content or NULL if it was empty.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   */
  protected function loadDefinitionFile($type) {
    //$definition = $this->getPluginDefinition();
    // $module = $this->moduleHandler->getModule($this->getPluginDefinition()['provider']);
    $path = drupal_get_path('module', 'fontanalib_graphql');
    $file = "{$path}/graphql/page/fontanalib_page.{$type}.graphqls";

    if (!file_exists($file)) {
      throw new InvalidPluginDefinitionException(sprintf("Missing schema definition file at %s.", $file));
    }

    return file_get_contents($file) ?: NULL;
  }
}
