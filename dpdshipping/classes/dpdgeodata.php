<?php
if (!defined('_PS_VERSION_'))
 exit;

function isDPDGeoData($mixed){
	return (get_class($mixed) == 'DPDGeoData');
}

class DPDGeoData
{
	public $long;
	public $lat;
	
	public function __construct($long, $lat)
	{
		$this->long = (float)$long;
		$this->lat = (float)$lat;
	}
}