<?php

namespace Ujamii\Geocoder\Service;

use Geocoder\Exception\Exception;
use Geocoder\Geocoder;
use Geocoder\Location;
use Neos\ContentRepository\Domain\Model\NodeInterface;
use Neos\Flow\Annotations as Flow;
use Psr\Log\LoggerInterface;

/**
 * @Flow\Scope("singleton")
 */
class GeocodingService
{

    /**
     * @var string
     * @Flow\InjectConfiguration("GeocodingService.observedNodeType")
     */
    protected $observedNodeType;

    /**
     * @var array
     * @Flow\InjectConfiguration("GeocodingService.observedProperties")
     */
    protected $observedProperties = [];

    /**
     * @var array
     * @Flow\InjectConfiguration("GeocodingService.mandatoryProperties")
     */
    protected $mandatoryProperties = [];

    /**
     * @var LoggerInterface
     * @Flow\Inject
     */
    protected $logger;

    /**
     * @var Geocoder
     * @Flow\Inject
     */
    protected $geocoder;

    /**
     * @param NodeInterface $node
     *
     * @throws \Neos\Flow\Http\Client\InfiniteRedirectionException
     */
    public function nodeUpdated(NodeInterface $node)
    {
        if ($node->getNodeType()->isOfType($this->observedNodeType)) {
            foreach ($this->mandatoryProperties as $mandatoryPropertyName) {
                if (empty($node->getProperty($mandatoryPropertyName))) {
                    return;
                }
            }

            $queryParts = [];
            foreach ($this->observedProperties as $observedProperty) {
                $queryParts[] = $node->getProperty($observedProperty);
            }

            $geoQuery  = implode(' ', $queryParts);
            $lastQuery = $node->getProperty('lastGeoQuery');

            // we only need to update if new data is set and differs from the old.
            if (empty($lastQuery) || $lastQuery != $geoQuery) {
                $node->setProperty('latitude', '');
                $node->setProperty('longitude', '');

                try {
                    $latLon = $this->geocodeLatLonFromAddress($geoQuery);
                    if (null !== $latLon) {
                        $node->setProperty('latitude', $latLon->getCoordinates()->getLatitude());
                        $node->setProperty('longitude', $latLon->getCoordinates()->getLongitude());
                        $node->setProperty('lastGeoQuery', $geoQuery);
                    }
                } catch (Exception $e) {
                    $this->logger->error($e->getMessage());
                }
            }
        }
    }

    /**
     * @param string $address
     *
     * @return ?Location
     * @throws \Geocoder\Exception\Exception
     */
    public function geocodeLatLonFromAddress(string $address): ?Location
    {
        $result = $this->geocoder->geocode($address);

        if ( ! $result->isEmpty()) {
            return $result->first();
        }

        return null;
    }

}
