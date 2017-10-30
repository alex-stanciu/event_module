<?php

namespace Drupal\event\Plugin\rest\resource;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Class EventRestResource.
 *
 * @package Drupal\event\Plugin\rest\Resource
 *
 * @RestResource(
 *     id = "event",
 *     label = @Translation("Event rest resource"),
 *     uri_paths = {
 *          "canonical" = "/event"
 *     }
 * )
 */
class EventRestResource extends ResourceBase {

    /**
     * The current request.
     *
     * @var \Symfony\Component\HttpFoundation\Request
     */
    protected $currentRequest;
    /**
     * The entity type manager service.
     *
     * @var \Drupal\Core\Entity\EntityTypeManagerInterface
     */
    protected $entityTypeManager;
    /**
     * The language manager service.
     *
     * @var \Drupal\Core\Language\LanguageManagerInterface
     */
    protected $languageManager;

    /**
     * The date format that will be used to provide the date.
     */
    const DATE_FORMAT = 'Y-m-d';

    /**
     * {@inheritdoc}
     */
    public function __construct(array $configuration, $plugin_id, $plugin_definition, array $serializer_formats, LoggerInterface $logger, Request $current_request, EntityTypeManagerInterface $entity_type_manager, LanguageManagerInterface $language_manager) {
        parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);
        $this->currentRequest = $current_request;
        $this->entityTypeManager = $entity_type_manager;
        $this->languageManager = $language_manager;
    }

    /**
     * {@inheritdoc}
     */
    public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
        return new static (
            $configuration,
            $plugin_id,
            $plugin_definition,
            $container->getParameter('serializer.formats'),
            $container->get('logger.factory')->get('event'),
            $container->get('request_stack')->getCurrentRequest(),
            $container->get('entity_type.manager'),
            $container->get('language_manager')
        );
    }

    /**
     * Responds to GET requests.
     *
     * Provides event entities that fall between the start and end dates (if specified). If no start date is specified,
     * the current date is used.
     *
     * @return \Drupal\rest\ResourceResponse
     *   The event entities.
     */
    public function get() {
        // Gets the start and end dates from the request object.
        $start = $this->getStartDate();
        $end = $this->getEndDate();
        // Creates the entity query for the events.
        $events = $this->entityTypeManager->getStorage('node')->getQuery()
            ->condition('type', 'event')
            ->condition('status', 1)
            ->condition('langcode', $this->languageManager->getCurrentLanguage()->getId())
            ->condition('field_date', $start->format(static::DATE_FORMAT), '>=')
            ->sort('field_date', 'ASC');
        if (!empty($end)) {
            // If the end date is set, it must be greater than the start date.
            if ($start->getTimestamp() - $end->getTimestamp() > 0) {
                throw new BadRequestHttpException($this->t('The end date cannot be lower than the start date.'));
            }
            // Adds the end date condition.
            $events->condition('field_date', $end->format(static::DATE_FORMAT), '<=');
        }
        $events = $events->execute();
        $events = $this->entityTypeManager->getStorage('node')->loadMultiple($events);

        // Creates the response object.
        $response = new ResourceResponse(array_values($events));

        // Create cacheable metadata to make sure we vary the response based on the url query arguments.
        $cache_metadata = new CacheableMetadata();
        $cache_metadata->setCacheContexts(['url.query_args:start', 'url.query_args:end']);
        $response->addCacheableDependency($cache_metadata);

        return $response;
    }

    /**
     * Provides a start date based on the current request. If the argument is missing, the current date is returned.
     *
     * @return DrupalDateTime
     *   The start date object.
     *
     * @throws BadRequestHttpException
     *   An exception is thrown in case the date is invalid, for any reason.
     */
    protected function getStartDate() {
        $start = $this->currentRequest->get('start');
        if (!empty($start)) {
            // Since we are using user-provided data, we must expect an exception, as the data could be invalid.
            try {
                $start = DrupalDateTime::createFromFormat(static::DATE_FORMAT, $start);
            }
            catch (\Exception $e) {
                $this->logger->error($e->getMessage() . $e->getTraceAsString());
                throw new BadRequestHttpException($this->t('The start date must use this format: @format', ['@format' => static::DATE_FORMAT]));
            }
        }
        else {
            // In case the start argument is missing, use the current date as start.
            $start = new DrupalDateTime('now');
        }
        return $start;
    }

    /**
     * Provides an end date based on the current request.
     *
     * @return DrupalDateTime|null
     *   The end date object, or NULL if it was not set.
     *
     * @throws BadRequestHttpException
     *   An exception is thrown in case the date is invalid, for any reason.
     */
    protected function getEndDate() {
        $end = $this->currentRequest->get('end');
        if (!empty($end)) {
            // Since we are using user-provided data, we must expect an exception, as the data could be invalid.
            try {
                return DrupalDateTime::createFromFormat(static::DATE_FORMAT, $end);
            }
            catch (\Exception $e) {
                $this->logger->error($e->getMessage() . $e->getTraceAsString());
                throw new BadRequestHttpException($this->t('The end date must use this format: @format', ['@format' => static::DATE_FORMAT]));
            }
        }
        return NULL;
    }

}
