<?php
if (!defined('_PS_VERSION_'))
 exit;

function isDPDAddress($mixed){
	return (get_class($mixed) == 'DPDAddress');
}

class DPDAddress
{
	public $company;
	public $salutation;
	public $name;
	public $street;
	public $houseNo;
	public $zipCode;
	public $city;
	public $country;
	public $mail;
	
	public function __construct($street, $houseNo, $zipCode, $city, $country)
	{
		$this->street = (string)$street;
		$this->houseNo = (string)$houseNo;
		$this->zipCode = (string)$zipCode;
		$this->city = (string)$city;
		$this->country = (string)$country;
	}
}