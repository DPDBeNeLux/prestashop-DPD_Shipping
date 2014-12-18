<?php
// This provides the locator with the json data it needs to dispaly the shops.

if (!defined('_PS_VERSION_'))
 exit;
	
class DpdShippingParcelShopLocatorModuleFrontController extends ModuleFrontController
{
	public function __construct()
	{
		$this->delisID = Configuration::get('DPDSHIPPING_DELIS_ID');
		$this->delisPw = Configuration::get('DPDSHIPPING_DELIS_PASSWORD');

		$this->live = Configuration::get('DPDSHIPPING_LIVE_SERVER') == 1 ? true : false;
		
		$connection = new DPDCore($this->delisID, $this->delisPw, $this->live); 
		$dpdGeoData = new DPDGeoData($_POST['long'], $_POST['lat']);
		
		echo json_encode($connection->findParcelShop($dpdGeoData));
		
		die();
	}
}