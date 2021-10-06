<?php

namespace Makiavelo\Flex\Traits;

/**
 * Trait to add latitude and longitude to the model
 * and also some utility functions for distance measuring.
 */
trait Geopositioned
{
    public $lat;
    public $lng;

    /**
     * Calculate distance from this model to a lat/lng pair
     * 
     * @param mixed $lat
     * @param mixed $lng
     * 
     * @return float
     */
    public function distanceTo($lat, $lng)
    {
        return $this->_vincentyFormulaDistance($this->getLat(), $this->getLng(), $lat, $lng);
    }

    /**
     * Calculates the great-circle distance between two points, with
     * the Vincenty formula.
     * 
     * @param float $latFrom Latitude of start point in [deg decimal]
     * @param float $lngFrom Longitude of start point in [deg decimal]
     * @param float $latTo Latitude of target point in [deg decimal]
     * @param float $lngTo Longitude of target point in [deg decimal]
     * 
     * @return float Distance between points in [m] (same as earthRadius)
     */
    public function _vincentyFormulaDistance($latFrom, $lngFrom, $latTo, $lngTo)
    {
        $earthRadius = 6371000; // In meters
        $latFrom = deg2rad($latFrom);
        $lngFrom = deg2rad($lngFrom);
        $latTo = deg2rad($latTo);
        $lngTo = deg2rad($lngTo);

        $lngDelta = $lngTo - $lngFrom;
        $a = pow(cos($latTo) * sin($lngDelta), 2) + pow(cos($latFrom) * sin($latTo) - sin($latFrom) * cos($latTo) * cos($lngDelta), 2);
        $b = sin($latFrom) * sin($latTo) + cos($latFrom) * cos($latTo) * cos($lngDelta);
  
        $angle = atan2(sqrt($a), $b);
        return $angle * $earthRadius;
    }
}