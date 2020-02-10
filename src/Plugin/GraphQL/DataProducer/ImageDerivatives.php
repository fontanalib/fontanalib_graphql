<?php

namespace Drupal\fontanalib_graphql\Plugin\GraphQL\DataProducer;

use Drupal\Core\Cache\RefinableCacheableDependencyInterface;
use Drupal\file\FileInterface;
use Drupal\graphql\Plugin\GraphQL\DataProducer\DataProducerPluginBase;
use Drupal\image\Entity\ImageStyle;

/**
 * @DataProducer(
 *   id = "image_derivatives",
 *   name = @Translation("Image Derivatives"),
 *   description = @Translation("Returns an array image derivative."),
 *   produces = @ContextDefinition("any",
 *     label = @Translation("Image derivative properties")
 *   ),
 *   consumes = {
 *     "entity" = @ContextDefinition("entity",
 *       label = @Translation("Entity"),
 *       required = FALSE
 *     ),
 *     "styles" = @ContextDefinition("any",
 *       label = @Translation("Array of Image styles")
 *     )
 *   }
 * )
 */
class ImageDerivatives extends DataProducerPluginBase {

  /**
   * @param \Drupal\file\FileInterface $entity
   *
   * @param $style
   * @param \Drupal\Core\Cache\RefinableCacheableDependencyInterface $metadata
   *
   * @return mixed
   */
  public function resolve(FileInterface $entity = NULL, $styles, RefinableCacheableDependencyInterface $metadata) {
    // Return if we dont have an entity.
    if (!$entity) {
      return NULL;
    }

    $access = $entity->access('view', NULL, TRUE);
    $metadata->addCacheableDependency($access);
    
    if ($access->isAllowed()) {
      if(!is_array($styles)){
        return $this->getImage($styles, $entity, $metadata);
      }

      $images = [];
      foreach($styles as $style){
        $images[$style]=$this->getImage($style, $entity, $metadata);
      }
      return $images;
    }

    return NULL;
  }
  private function getImage($style, $entity, $metadata){
    if($image_style = ImageStyle::load($style)){
      $width = $entity->width;
      $height = $entity->height;

      if (empty($width) || empty($height)) {
        /** @var \Drupal\Core\Image\ImageInterface $image */
        $image = \Drupal::service('image.factory')->get($entity->getFileUri());
        if ($image->isValid()) {
          $width = $image->getWidth();
          $height = $image->getHeight();
        }
      }
      // Determine the dimensions of the styled image.
      $dimensions = [
        'width' => $width,
        'height' => $height,
      ];

      $image_style->transformDimensions($dimensions, $entity->getFileUri());
      $metadata->addCacheableDependency($image_style);

      return [
        'url' => $image_style->buildUrl($entity->getFileUri()),
        'width' => $dimensions['width'],
        'height' => $dimensions['height'],
      ];
    }
    return NULL;
  }

}
