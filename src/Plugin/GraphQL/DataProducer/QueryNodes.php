<?php

namespace Drupal\fontanalib_graphql\Plugin\GraphQL\DataProducer;

use Drupal\Core\Cache\RefinableCacheableDependencyInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\graphql\Plugin\GraphQL\DataProducer\DataProducerPluginBase;
use Drupal\fontanalib_graphql\Wrappers\QueryConnection;
use GraphQL\Error\UserError;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @DataProducer(
 *   id = "query_nodes",
 *   name = @Translation("Load Nodes"),
 *   description = @Translation("Loads a list of nodes."),
 *   produces = @ContextDefinition("any",
 *     label = @Translation("Node connection")
 *   ),
 *   consumes = {
 *     "entityType" = @ContextDefinition("string",
 *       label = @Translation("entityType"),
 *       required = TRUE
 *     ),
 *     "offset" = @ContextDefinition("integer",
 *       label = @Translation("Offset"),
 *       required = FALSE
 *     ),
 *     "limit" = @ContextDefinition("integer",
 *       label = @Translation("Limit"),
 *       required = FALSE
 *     ),
 *    "filter" = @ContextDefinition("any",
 *       label = @Translation("Filter"),
 *       required = FALSE
 *     ),
 *    "sort" = @ContextDefinition("any",
 *       label = @Translation("Sort"),
 *       required = FALSE
 *     )
 *   }
 * )
 */
class QueryNodes extends DataProducerPluginBase implements ContainerFactoryPluginInterface {

  const MAX_LIMIT = 100;

  const ENUM_MAP = [
    "EQUAL" => "=",
    "NOT_EQUAL" => "<>",
    "LESS_THAN" => "<",
    "LESS_THAN_OR_EQUAL" => "<=",
    "GREATER_THAN" => ">",
    "GREATER_THAN_OR_EQUAL" => ">=",
    "NOT_IN" => "NOT IN",
    "IN" => "IN",
    "BETWEEN" => "BETWEEN",
    "NOT_BETWEEN" => "NOT BETWEEN",
    "IS_NULL" => "IS NULL",
    "IS_NOT_NULL" => "IS NOT NULL",
    "EXISTS" => "EXISTS",
    "NOT_EXISTS" => "NOT EXISTS",
    "LIKE"  => "LIKE",
    "NOT_LIKE" => "NOT LIKE"
  ];
  /**
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityManager;

  protected $allowedFilters = [

  ];

  /**
   * {@inheritdoc}
   *
   * @codeCoverageIgnore
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity.manager')
    );
  }

  /**
   * Nodes constructor.
   *
   * @param array $configuration
   *   The plugin configuration.
   * @param string $pluginId
   *   The plugin id.
   * @param mixed $pluginDefinition
   *   The plugin definition.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityManager
   *
   * @codeCoverageIgnore
   */
  public function __construct(
    array $configuration,
    $pluginId,
    $pluginDefinition,
    EntityTypeManagerInterface $entityManager
  ) {
    parent::__construct($configuration, $pluginId, $pluginDefinition);
    $this->entityManager = $entityManager;
  }

  /**
   * @param $entityType
   * @param $offset
   * @param $limit
   * @param $filter
   * @param $sort
   * @param \Drupal\Core\Cache\RefinableCacheableDependencyInterface $metadata
   *
   * @return \Drupal\fontanalib_graphql\Wrappers\QueryConnection
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function resolve($entityType, $offset, $limit, $filter, $sort, RefinableCacheableDependencyInterface $metadata) {
    if (!$limit > static::MAX_LIMIT) {
      throw new UserError(sprintf('Exceeded maximum query limit: %s.', static::MAX_LIMIT));
    }
    $bundles = array_keys(\Drupal::cache('fontanalib_graphql')->get('bundles')->data);
  
    $storage = $this->entityManager->getStorage($entityType);
    $type = $storage->getEntityType();
    $query = $storage->getQuery()
      ->currentRevision()
      ->accessCheck();

    $query->range($offset, $limit);
    if (!empty($filter) && is_array($filter)) {
      $filterConditions = $this->buildFilterConditions($query, $filter);
      if (count($filterConditions->conditions())) {
        $query->condition($filterConditions);
      }
    }
    $query->condition('status', 1);
    if($entityType == 'node'){
      $query->condition('type', $bundles, "IN");
    }
    $query->condition('status', 1);
    // if($storage->hasField('field_private')){
      $public = $query->orConditionGroup()
                ->notExists('field_private')
                ->condition('field_private', 1, "<>");
    //   $query->condition('field_private', 1, "<>");
    // }
    $query->condition($public);
  
    if($sort && !empty($sort)){
      $query->sort($sort['sortBy'], $sort['order']);
    } elseif($entityType == 'node') {
      $query->sort('created', 'DESC');
    }
    $metadata->addCacheTags($type->getListCacheTags()); 
    $metadata->addCacheContexts($type->getListCacheContexts());
    $query->addMetaData('graphql_context',[
      'parent'=> $entityType,
      'offset'=> $offset,
      'limit'=>$limit,
      'filter'=>serialize($filter),
      'sort'=>serialize($sort)
      ]);

    return new QueryConnection($query);
  }
  /**
   * Recursively builds the filter condition groups.
   *
   * @param \Drupal\fontanalib_graphql\Wrappers\QueryConnection $query
   *   The entity query object.
   * @param array $filter
   *   The filter definitions from the field arguments.
   *
   * @return \Drupal\Core\Entity\Query\ConditionInterface
   *   The generated condition group according to the given filter definitions.
   *
   * @throws \GraphQL\Error\UserError
   *   If the given operator and value for a filter are invalid.
   */
  public function buildFilterConditions($query, $filter){
    $conjunction = !empty($filter['conjunction']) ? $filter['conjunction'] : 'AND';
    $group = $conjunction === 'AND' ? $query->andConditionGroup() : $query->orConditionGroup();
    // Apply filter conditions.
    $conditions = !empty($filter['conditions']) ? $filter['conditions'] : [];
    foreach ($conditions as $condition) {
      // Check if we need to disable this condition.
      if (isset($condition['enabled']) && empty($condition['enabled'])) {
        continue;
      }

      $field = $condition['field'];
      $value = !empty($condition['value']) ? $condition['value'] : NULL;
      $operator = !empty($condition['operator']) ? static::ENUM_MAP[$condition['operator']] : NULL;
      // $language = !empty($condition['language']) ? $condition['language'] : NULL;

      // We need at least a value or an operator.
      if (empty($operator) && empty($value)) {
        throw new UserError(sprintf("Missing value and operator in filter for '%s'.", $field));
      }
      // Unary operators need a single value.
      else if (!empty($operator) && $this->isUnaryOperator($operator)) {
        if (empty($value) || (is_array($value) && count($value) > 1)) {
          throw new UserError(sprintf("Unary operators must be associated with a single value (field '%s').", $field));
        }

        // Pick the first item from the values.
        $value = is_array($value) ? reset($value) : $value;
      }
      // Range operators need exactly two values.
      else if (!empty($operator) && $this->isRangeOperator($operator)) {
        if (empty($value) || !is_array($value) || count($value) !== 2) {
          throw new UserError(sprintf("Range operators must require exactly two values (field '%s').", $field));
        }
      }
      // Null operators can't have a value set.
      else if (!empty($operator) && $this->isNullOperator($operator)) {
        if (!empty($value)) {
          throw new UserError(sprintf("Null operators must not be associated with a filter value (field '%s').", $field));
        }
      }

      // If no operator is set, however, we default to EQUALS or IN, depending
      // on whether the given value is an array with one or more than one items.
      if (empty($operator)) {
        $value = is_array($value) && count($value) === 1 ? reset($value) : $value;
        $operator = is_array($value) ? 'IN' : '=';
      }

      // Add the condition for the current field.
      $group->condition($field, $value, $operator);
      //$group->condition($field, $value, $operator, $language);
    }

    // Apply nested filter group conditions.
    $groups = !empty($filter['groups']) ? $filter['groups'] : [];
    foreach ($groups as $args) {
      // By default, we use AND condition groups.
      // Conditions can be disabled. Check we are not adding an empty condition group.
      $filterConditions = $this->buildFilterConditions($query, $args);
      if (count($filterConditions->conditions())) {
        $group->condition($filterConditions);
      }
    }
    return $group;
  }
  /**
   * Checks if an operator is a unary operator.
   *
   * @param string $operator
   *   The query operator to check against.
   *
   * @return bool
   *   TRUE if the given operator is unary, FALSE otherwise.
   */
  protected function isUnaryOperator($operator) {
    $unary = ["=", "<>", "<", "<=", ">", ">=", "LIKE", "NOT LIKE"];
    return in_array($operator, $unary);
  }

  /**
   * Checks if an operator is a null operator.
   *
   * @param string $operator
   *   The query operator to check against.
   *
   * @return bool
   *   TRUE if the given operator is a null operator, FALSE otherwise.
   */
  protected function isNullOperator($operator) {
    $null = ["IS NULL", "IS NOT NULL"];
    return in_array($operator, $null);
  }

  /**
   * Checks if an operator is a range operator.
   *
   * @param string $operator
   *   The query operator to check against.
   *
   * @return bool
   *   TRUE if the given operator is a range operator, FALSE otherwise.
   */
  protected function isRangeOperator($operator) {
    $null = ["BETWEEN", "NOT BETWEEN"];
    return in_array($operator, $null);
  }
}
