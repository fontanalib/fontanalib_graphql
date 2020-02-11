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
    $this->bundleMap = $this->getConfiguredBundles();
    
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
    $this->addEntityFields($registry, $builder);

    // Re-usable connection type fields.
    $this->addConnectionFields('NodeConnection', $registry, $builder);
    $this->addConnectionFields('TermConnection', $registry, $builder);

    return $registry;
  }

  /**
   * @param \Drupal\graphql\GraphQL\ResolverRegistry $registry
   * @param \Drupal\graphql\GraphQL\ResolverBuilder $builder
   */
  protected function addEntityFields(ResolverRegistry $registry, ResolverBuilder $builder) {   
    

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

    $registry->addFieldResolver('Term', 'id',
      $builder->produce('entity_id')
        ->map('entity', $builder->fromParent())
    );
    $registry->addFieldResolver('Term', 'name',
      $builder->produce('entity_label')
        ->map('entity', $builder->fromParent())
    );

    $registry->addFieldResolver('Term', 'vid',
      $builder->produce('entity_bundle')
        ->map('entity', $builder->fromParent())
    );
  }

  /**
   * @param \Drupal\graphql\GraphQL\ResolverRegistry $registry
   * @param \Drupal\graphql\GraphQL\ResolverBuilder $builder
   */
  protected function addQueryFields(ResolverRegistry $registry, ResolverBuilder $builder) {
    
    // $this->bundles = array_keys($this->bundleMap);
    $registry->addFieldResolver('Query', 'nodes',
      $builder->produce('query_nodes')
        ->map('entityType', $builder->fromValue('node'))
        ->map('offset', $builder->fromArgument('offset'))
        ->map('limit', $builder->fromArgument('limit'))
        ->map('filter', $builder->fromArgument('filter'))
        ->map('sort', $builder->fromArgument('sort'))
    );
    // $this->bundles = array_keys($this->bundleMap);
    $registry->addFieldResolver('Query', 'terms',
      $builder->produce('query_nodes')
        ->map('entityType', $builder->fromValue('taxonomy_term'))
        ->map('offset', $builder->fromArgument('offset'))
        ->map('limit', $builder->fromArgument('limit'))
        ->map('filter', $builder->fromArgument('filter'))
        ->map('sort', $builder->fromArgument('sort'))
    );
    $registry->addFieldResolver('Query', 'route', $builder->compose(
      $builder->produce('route_load')
        ->map('path', $builder->fromArgument('path')),
      $builder->produce('route_entity')
        ->map('url', $builder->fromParent())
    ));
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

  protected function getConfiguredBundles(){
    $cache=\Drupal::cache('fontanalib_graphql')->get('bundles');
    $bundles =  $cache ? $cache->data : [];
    if(\Drupal::config('graphql.config')->get('development') == TRUE || empty($bundles)){
      $path = drupal_get_path('module', 'fontanalib_graphql') . "/graphql";
      $dir = new \DirectoryIterator($path);
      foreach ($dir as $fileinfo) {
        if ($fileinfo->isDir() && !$fileinfo->isDot()) {
          $base_name=$fileinfo->getFilename();
          $bundles[$base_name] = str_replace(" ", "", ucwords(str_replace("_", " ",$base_name)));
        }
      }
      \Drupal::cache('fontanalib_graphql')->set('bundles', $bundles, \Drupal\Core\Cache\CacheBackendInterface::CACHE_PERMANENT);
    }
    return $bundles;
  }
}
