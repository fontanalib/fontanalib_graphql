<?php

namespace Drupal\fontanalib_graphql\Plugin\GraphQL\Schema;
use Drupal\node\NodeInterface;
use Drupal\graphql\GraphQL\ResolverBuilder;
use Drupal\graphql\GraphQL\ResolverRegistry;
use Drupal\graphql\Plugin\GraphQL\Schema\SdlSchemaPluginBase;
use Drupal\fontanalib_graphql\Wrappers\QueryConnection;
use GraphQL\Error\UserError;

use Drupal\graphql\Plugin\GraphQL\DataProducer\Entity\Fields\Image\ImageDerivative;

/**
 * @Schema(
 *   id = "fontanalib",
 *   name = "Fontanalib schema"
 * )
 */
class FontanalibSchema extends SdlSchemaPluginBase {
  public $bundleMap;
  /**
   * {@inheritdoc}
   */
  public function getResolverRegistry() {
    $this->bundleMap = array(
      "article" => "Article",
      "page"    => "Page"
    );
    // Set default values for config which require dynamic values.
    \Drupal::configFactory()->getEditable('fontanalib_graphql.settings')->set('bundles', $this->bundleMap)
    ->save();
    $builder = new ResolverBuilder();
    $registry = new ResolverRegistry();

    $registry->addTypeResolver('Node', function ($value) {
      if(!$value instanceOf NodeInterface){
        throw new UserError(sprintf('Item not a node: %s.', print_r(get_class($value), TRUE)));
      }
      if (!isset($this->bundleMap[$value->bundle()])) {
        throw new UserError(sprintf('Node bundle not configured for graphql: %s.', print_r($value->bundle(), TRUE)));
      }
      return $this->bundleMap[$value->bundle()];
    });
    $this->addQueryFields($registry, $builder);
    $this->addArticleFields($registry, $builder);

    // Re-usable connection type fields.
    $this->addConnectionFields('NodeConnection', $registry, $builder);

    return $registry;
  }

  /**
   * @param \Drupal\graphql\GraphQL\ResolverRegistry $registry
   * @param \Drupal\graphql\GraphQL\ResolverBuilder $builder
   */
  protected function addArticleFields(ResolverRegistry $registry, ResolverBuilder $builder) {   
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

    $registry->addFieldResolver('User', 'name',
      $builder->produce('property_path')
        ->map('type', $builder->fromValue("entity:user"))
        ->map('value', $builder->fromParent())
        ->map('path', $builder->fromValue('field_display_name.value'))
        ->map('entity', $builder->fromParent())
    );

    $registry->addFieldResolver('User', 'mail',
      $builder->produce('property_path')
        ->map('type', $builder->fromValue("entity:user"))
        ->map('value', $builder->fromParent())
        ->map('path', $builder->fromValue("mail.value"))
        ->map('entity', $builder->fromParent())
    );

    $registry->addFieldResolver('User', 'picture',
      $builder->compose(
        $builder->produce('property_path')
          ->map('type', $builder->fromValue("entity:file"))
          ->map('value', $builder->fromParent())
          ->map('path', $builder->fromValue("user_picture.entity")),
        $builder->produce('image_derivatives')
        ->map('entity', $builder->fromParent())
        ->map('styles', $builder->fromArgument('size'))
      )
    );

  }

  /**
   * @param \Drupal\graphql\GraphQL\ResolverRegistry $registry
   * @param \Drupal\graphql\GraphQL\ResolverBuilder $builder
   */
  protected function addQueryFields(ResolverRegistry $registry, ResolverBuilder $builder) {
    $registry->addFieldResolver('Query', 'article',
      $builder->produce('entity_load')
        ->map('type', $builder->fromValue('node'))
        ->map('bundles', $builder->fromValue(['article']))
        ->map('id', $builder->fromArgument('id'))
    );
    // $this->bundles = array_keys($this->bundleMap);
    $registry->addFieldResolver('Query', 'nodes',
      $builder->produce('query_nodes')
        ->map('offset', $builder->fromArgument('offset'))
        ->map('limit', $builder->fromArgument('limit'))
        ->map('filter', $builder->fromArgument('filter'))
        ->map('sort', $builder->fromArgument('sort'))
    );
  }

  /**
   * @param string $type
   * @param \Drupal\graphql\GraphQL\ResolverRegistry $registry
   * @param \Drupal\graphql\GraphQL\ResolverBuilder $builder
   */
  protected function addConnectionFields($type, ResolverRegistry $registry, ResolverBuilder $builder) {
    $registry->addFieldResolver($type, 'total',
      $builder->callback(function (QueryConnection $connection) {
        return $connection->total();
      })
    );

    $registry->addFieldResolver($type, 'items',
      $builder->callback(function (QueryConnection $connection) {
        return $connection->items();
      })
    );
  }
}
