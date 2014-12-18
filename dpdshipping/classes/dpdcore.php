<?php
if (!defined('_PS_VERSION_'))
 exit;
	
class DPDCore
{
	public $delisID = "KD249300F8";
	public	$password = "79cdd";
	
	public $core_base_url = 'https://public-ws-stage.dpd.com/services/';
	
	public function __construct($delisID, $password, $live = false)
	{
		$this->delisID = $delisID;
		$this->password = $password;
		
		if($live)
		{
			$this->core_base_url = 'https://public-ws.dpd.com/services/';
		}
	}
	
	public function autenticate(){
		$url = $this->core_base_url."LoginService/V2_0/?wsdl";
		$function = "getAuth";
		$body = array(
			"delisId" => $this->delisID,
			"password" => $this->password,
			"messageLanguage" => "en_Us"
		);
		
		$result;
		try{
			$result = $this->coreCall($url, $function, $body);
		} catch (Exception $e){
			throw new Exception("Something went wrong with ws authentication: "."/r/n".$e->getMessage());
		}
		
		return $result;
	}

	public function findParcelShop($data, $limit = 10){
		if (isDPDGeoData($data))
			return $this->findParcelShopByGeoData($data, $limit);
		else 
			throw new Exception(get_class($this).': findParcelShop expects GeoData.');
	}
	
	private function findParcelShopByGeoData($data, $limit)
	{
		$authenticationResult;
		try {
			$authenticationResult = $this->autenticate();
		} catch (Exception $e) {
			throw new Exception("Something went wrong authenticating:"."/r/n".$e->getMessage());
		}
	
		$url = $this->core_base_url."ParcelShopFinderService/V3_0/?wsdl";
		$function = 'findParcelShopsByGeoData';
		
		$header = array(
			"delisId" => $authenticationResult->return->delisId,
			"authToken" => $authenticationResult->return->authToken,
			"messageLanguage" => "en_US"
		);
		$body = array(
			"longitude" => $data->long,
			"latitude" => $data->lat,
			"limit" => $limit
		);
		
		$result = $this->coreCall($url, $function, $body, $header);
		
		return $this->processParcelShopFinderResult($result);
	}
	
	private function processParcelShopFinderResult($data){
		$output;
		
		foreach($data->parcelShop as $key => $shop){
			$newShop = new stdClass();
			
			$newShop->ParcelShopID = $shop->parcelShopId;
			
			$newShop->ShopAddress = new stdClass();
			$newShop->ShopAddress->Company = $shop->company;
			$newShop->ShopAddress->Street = $shop->street;
			$newShop->ShopAddress->HouseNo = $shop->houseNo;
			$newShop->ShopAddress->Country = $shop->isoAlpha2;
			$newShop->ShopAddress->ZipCode = $shop->zipCode;
			$newShop->ShopAddress->City = $shop->city;
			
			$newShop->GeoData = new stdClass();
			$newShop->GeoData->Distance = $shop->distance;
			$newShop->GeoData->Longitude = $shop->longitude;
			$newShop->GeoData->Latitude = $shop->latitude;
			
			$newShop->OpeningHoursList = new stdClass();
			foreach($shop->openingHours as $dKey => $day){
				$newShop->OpeningHoursList->OpeningHours[$dKey] = new stdClass();
				$newShop->OpeningHoursList->OpeningHours[$dKey]->WeekDay = $day->weekday;
				
				$newShop->OpeningHoursList->OpeningHours[$dKey]->OpenTimeList = new stdClass();
				$newShop->OpeningHoursList->OpeningHours[$dKey]->OpenTimeList->OpenTimeType[0] = new stdClass();
				$newShop->OpeningHoursList->OpeningHours[$dKey]->OpenTimeList->OpenTimeType[1] = new stdClass();
				
				$newShop->OpeningHoursList->OpeningHours[$dKey]->OpenTimeList->OpenTimeType[0]->TimeFrom = $day->openMorning;
				$newShop->OpeningHoursList->OpeningHours[$dKey]->OpenTimeList->OpenTimeType[0]->TimeEnd = $day->closeMorning;
				$newShop->OpeningHoursList->OpeningHours[$dKey]->OpenTimeList->OpenTimeType[1]->TimeFrom = $day->openAfternoon;
				$newShop->OpeningHoursList->OpeningHours[$dKey]->OpenTimeList->OpenTimeType[1]->TimeEnd = $day->closeAfternoon;
			}
			$output[$key] = $newShop;
		}
		
		return array('ResultCounter' => count($data->parcelShop), 'ParcelShops' => $output);
	}
	
	private function coreCall($url, $function, $params, $headerParams = false)
	{
		$client = new SoapClient($url, array('trace' => 1));
		
		if($headerParams){
			// Create (and set) new header with namespace, and parameters
			$sHeader = new SoapHeader('http://dpd.com/common/service/types/Authentication/2.0', 'authentication', $headerParams);
			$client->__setSoapHeaders(array($sHeader));
		}
		
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