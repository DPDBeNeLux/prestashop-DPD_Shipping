<?php
// This provides the locator with the json data it needs to dispaly the shops.

if (!defined('_PS_VERSION_'))
 exit;
	
class DpdShippingParcelShopLocatorModuleFrontController extends ModuleFrontController
{
	public function __construct()
	{
		$this->pc_name = Configuration::get('DPDSHIPPING_PC_NAME');
		$this->pc_token = Configuration::get('DPDSHIPPING_PC_TOKEN');
		$this->uc_cuid = Configuration::get('DPDSHIPPING_UC_CUID');
		$this->uc_token = Configuration::get('DPDSHIPPING_UC_TOKEN');
		
		$this->live = Configuration::get('DPDSHIPPING_LIVE_SERVER') == 1 ? true : false;
		
		$connection = new DPDCloud($this->pc_name, $this->pc_token, $this->uc_cuid, $this->uc_token, $this->live); 
		$dpdGeoData = new DPDGeoData($_POST['long'], $_POST['lat']);
		
		echo json_encode($connection->findParcelShop($dpdGeoData));
		
		die();
	}
}