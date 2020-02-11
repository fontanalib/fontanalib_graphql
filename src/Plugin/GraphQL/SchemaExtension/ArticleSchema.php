<?php

namespace Drupal\fontanalib_graphql\Plugin\GraphQL\SchemaExtension;

use Drupal\graphql\GraphQL\ResolverBuilder;
use Drupal\graphql\GraphQL\ResolverRegistryInterface;
use Drupal\graphql\Plugin\GraphQL\SchemaExtension\SdlSchemaExtensionPluginBase;

/**
 * @SchemaExtension(
 *   id = "fontanalib_article",
 *   name = "Article extension",
 *   description = "A simple extension that adds node related fields for articles.",
 *   schema = "fontanalib"
 * )
 */
class ArticleSchema extends SdlSchemaExtensionPluginBase {
  /**
   * {@inheritdoc}
   */
  public function registerResolvers(ResolverRegistryInterface $registry) {
    $builder = new ResolverBuilder();

    $this->addQueryFields($registry, $builder);
    $this->addArticleFields($registry, $builder);
  }

  /**
   * @param \Drupal\graphql\GraphQL\ResolverRegistryInterface $registry
   * @param \Drupal\graphql\GraphQL\ResolverBuilder $builder
   */
  protected function addArticleFields(ResolverRegistryInterface $registry, ResolverBuilder $builder) {
    $registry->addFieldResolver('Article', 'id',
      $builder->produce('entity_id')
        ->map('entity', $builder->fromParent())
    );

    $registry->addFieldResolver('Article', 'title',
      $builder->produce('entity_label')
        ->map('entity', $builder->fromParent())
    );

    $registry->addFieldResolver('Article', 'alias',
      $builder->produce('entity_alias')
        ->map('entity', $builder->fromParent())
    );
    
    $registry->addFieldResolver('Article', 'author',
      $builder->produce('entity_owner')
        ->map('entity', $builder->fromParent())
    );

    $registry->addFieldResolver('Article', 'featured_image',
      $builder->compose(
        $builder->produce('property_path')
        ->map('type', $builder->fromValue("entity:file"))
        ->map('value', $builder->fromParent())
        ->map('path', $builder->fromValue("field_image.entity")),
        $builder->produce('image_derivatives')
        ->map('entity', $builder->fromParent())
        ->map('styles', $builder->fromArgument('size')) 
      )
    );
    $registry->addFieldResolver('Article', 'body',
      $builder->compose(
        $builder->produce('property_path')
        ->map('type', $builder->fromValue("entity:node"))
        ->map('value', $builder->fromParent())
        ->map('path', $builder->fromValue("body.processed")),
        $builder->produce('text_html')
        ->map('string', $builder->fromParent()) 
      )
  );
  $registry->addFieldResolver('Article', 'tags',
      $builder->produce('entity_reference')
        ->map('entity', $builder->fromParent())
        ->map('field', $builder->fromValue('field_tags'))
    );
  }

  /**
   * @param \Drupal\graphql\GraphQL\ResolverRegistryInterface $registry
   * @param \Drupal\graphql\GraphQL\ResolverBuilder $builder
   */
  protected function addQueryFields(ResolverRegistryInterface $registry, ResolverBuilder $builder) {
    $registry->addFieldResolver('Query', 'article',
      $builder->produce('entity_load')
        ->map('type', $builder->fromValue('node'))
        ->map('bundles', $builder->fromValue(['article']))
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
    $module = $this->moduleHandler->getModule($this->getPluginDefinition()['provider']);
    $file = "{$module->getPath()}/graphql/article/fontanalib_article.{$type}.graphqls";

    if (!file_exists($file)) {
      throw new InvalidPluginDefinitionException(sprintf("Missing schema definition file at %s.", $file));
    }

    return file_get_contents($file) ?: NULL;
  }
}
