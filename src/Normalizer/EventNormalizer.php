<?php

namespace Drupal\event\Normalizer;

use Drupal\node\Entity\Node;
use Drupal\serialization\Normalizer\NormalizerBase;

/**
 * Class EventNormalizer.
 *
 * @package Drupal\event\Normalizer
 */
class EventNormalizer extends NormalizerBase {

  /**
   * {@inheritdoc}
   */
  protected $supportedInterfaceOrClass = 'Drupal\node\NodeInterface';

  /**
   * {@inheritdoc}
   */
  public function normalize($object, $format = NULL, array $context = []) {
    $attributes = $object->toArray();
    if ($object instanceof Node && $object->getType() === 'event') {
      $attributes = [
        'title' => $object->title->value,
        'body' => $object->field_body->value,
        'date' => $object->field_date->value,
      ];
    }
    return $attributes;
  }

}
