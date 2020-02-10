<?php

namespace Drupal\fontanalib_graphql\Plugin\GraphQL\DataProducer;

use Drupal\graphql\Plugin\GraphQL\DataProducer\DataProducerPluginBase;

/**
 * @DataProducer(
 *   id = "text_html",
 *   name = @Translation("Text Html"),
 *   description = @Translation("Transforms a string to be ready to render as HTML."),
 *   produces = @ContextDefinition("string",
 *     label = @Translation("html string")
 *   ),
 *   consumes = {
 *     "string" = @ContextDefinition("string",
 *       label = @Translation("string")
 *     )
 *   }
 * )
 */
class TextHtml extends DataProducerPluginBase {

  /**
   * @param $string
   *
   * @return mixed
   */
  public function resolve($string) {
    return trim(str_replace(PHP_EOL, ' ', $string));
    //trim(preg_replace('/\s+/', '', $string));
  }

}
