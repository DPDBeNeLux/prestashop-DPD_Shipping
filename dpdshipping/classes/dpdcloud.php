<?php
if (!defined('_PS_VERSION_'))
 exit;
	
class DPDCloud
{
	public 	$pc_name;
	public	$pc_token;
	public	$uc_cuid;
	public	$uc_token;
	
	public $cloud_header = 'https://cloud.dpd.com/';
	public $cloud_url = 'https://cloud-stage.dpd.com/services/v1/DPDCloudService.asmx?wsdl';
	
	public $cloud_version = '100';
	public $cloud_language = 'en_EN';
	
	public function __construct($pc_name, $pc_token, $uc_cuid, $uc_token, $live = false)
	{
		$this->pc_name = $pc_name;
		$this->pc_token = $pc_token;
		$this->uc_cuid = $uc_cuid;
		$this->uc_token = $uc_token;
		
		if($live)
		{
			$this->cloud_url = 'https://cloud.dpd.com/services/v1/DPDCloudService.asmx?wsdl';
		}
	}
	
	public function testConnection(){
		$function = '';
		$body = array(
		'getParcelShopFinderRequest' => array(
			'Version'	=> $this->cloud_version,
			'Language'	=> $this->cloud_language,
			'PartnerCredentials'	=> array(
				'Name' => $this->pc_name,
				'Token' => $this->pc_token
				),
				'UserCredentials'	=> array(
					'cloudUserID' => $this->uc_cuid,
					'Token' => $this->uc_token
				)
			)
		);
		return true;
	}
	
	public function findParcelShop($data, $limit = 10){
		if (isDPDAddress($data))
			return $this->findParcelShopByAddress($data, $limit);
		elseif (isDPDGeoData($data))
			return $this->findParcelShopByGeoData($data, $limit);
		else 
			throw new Exception(get_class($this).': findParcelShop expects an Address or GeoData.');
	}
	
	public function setOrder($settings, $cnee_address, $parcel_details, $amount = 1){
		$function = 'setOrder';
		$body = array(
			'setOrderRequest' => array(
				'Version'	=> $this->cloud_version,
				'Language'	=> $this->cloud_language,
				'PartnerCredentials'	=> array(
					'Name' => $this->pc_name,
					'Token' => $this->pc_token
				),
				'UserCredentials'	=> array(
					'cloudUserID' => $this->uc_cuid,
					'Token' => $this->uc_token
				),
				'OrderAction'	=> 'startOrder',
				'OrderSettings'	=> array(
					'ShipDate'	=> date("Y-m-d\TH:i:s"),
					'LabelSize'	=> $settings['labelSize'],
					'LabelStartPosition'	=> $settings['labelStartPosition']
				),
				'OrderDataList'	=> array(
					'OrderData' => array(
						array(
							'ShipAddress' => array(
								'Company' => $cnee_address->company,
								'Salutation' => $cnee_address->salutation,
								'Name' => $cnee_address->name,
								'Street' => $cnee_address->street,
								'HouseNo' => $cnee_address->houseNo,
								'Country' => $cnee_address->country,
								'ZipCode' => $cnee_address->zipcode,
								'City' =>  $cnee_address->city,
								'Mail' => $cnee_address->mail
							)
						),
						'ParcelData' => array(
							'ShipService' => $parcel_details['service'],
							'Weight' => $parcel_details['weight'],
							'Content' => $parcel_details['content'],
							'YourInternalID' => $parcel_details['yourInternalID'],
							'Reference1' => $parcel_details['reference1']
						)
					)
				)
			)
		);
		return $this->cloudCall($function, $body);
	}
	
	private function findParcelShopByAddress($data, $limit)
	{
		$function = 'getParcelShopFinder';
		$body = array(
			'getParcelShopFinderRequest' => array(
				'Version'	=> $this->cloud_version,
				'Language'	=> $this->cloud_language,
				'PartnerCredentials'	=> array(
					'Name' => $this->pc_name,
					'Token' => $this->pc_token
				),
				'UserCredentials'	=> array(
					'cloudUserID' => $this->uc_cuid,
					'Token' => $this->uc_token
				),
				'MaxReturnValues'	=> $limit,
				'SearchMode'	=> 'SearchByAddress',
				'SearchAddress'	=> array(
					'Street'	=> $data->street,
					'HouseNo'	=> $data->houseNo,
					'Zipcode'	=> $data->zipCode,
					'City'	=> $data->city,
					'Country'	=> $data->country
				),
				'NeedService' => 'ConsigneePickup'
			)
		);
		$cloud_result = $this->cloudCall($function, $body);
		
		return $this->processParcelShopFinderResult($cloud_result);
	}
	
	private function findParcelShopByGeoData($data, $limit)
	{
		$function = 'getParcelShopFinder';
		$body = array(
			'getParcelShopFinderRequest' => array(
				'Version'	=> $this->cloud_version,
				'Language'	=> $this->cloud_language,
				'PartnerCredentials'	=> array(
					'Name' => $this->pc_name,
					'Token' => $this->pc_token
				),
				'UserCredentials'	=> array(
					'cloudUserID' => $this->uc_cuid,
					'Token' => $this->uc_token
				),
				'MaxReturnValues'	=> $limit,
				'SearchMode'	=> 'SearchByGeoData',
				'SearchGeoData'	=> array(
					'Longitude'	=> $data->long,
					'Latitude'	=> $data->lat
				),
				'NeedService' => 'ConsigneePickup'
			)
		);
		
		$cloud_result = $this->cloudCall($function, $body);
		
		return $this->processParcelShopFinderResult($cloud_result);
	}
	
	private function processParcelShopFinderResult($data){
		if ($data->getParcelShopFinderResult->Ack)
		{
			if ($data->getParcelShopFinderResult->ResultCounter > 0)
				return array('ResultCounter' => $data->getParcelShopFinderResult->ResultCounter, 'ParcelShops' => $data->getParcelShopFinderResult->ParcelShopList->ParcelShop);
			else
				return array('ResultCounter' => $data->getParcelShopFinderResult->ResultCounter);
		}
	}
	
	private function cloudCall($function, $params)
	{
		$client = new SoapClient($this->cloud_url, array('trace' => 1));
		
		$header = new SoapHeader($this->cloud_header, 'MichielVanGucht');
		$client->__setSoapHeaders(array($header));
		
		$result;
		try {
			$result = $client->__soapCall($function, array($params));
		} catch (SoapFault $fault) {
			throw new Exception(get_class($this).': An error occured during Soap call to server. '.$fault->getMessage().': '.$client->__getLastRequest());
		}
		
		unset($client);
	
		return $result;;
	}
}