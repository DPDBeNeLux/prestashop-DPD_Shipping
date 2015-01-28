<?php
// The module's main class. Will always be loaded.
if (!defined('_PS_VERSION_'))
 exit;
	
include_once dirname(__FILE__).'/classes/dpdcloud.php';
include_once dirname(__FILE__).'/classes/dpdcore.php';
include_once dirname(__FILE__).'/classes/dpdaddress.php';
include_once dirname(__FILE__).'/classes/dpdgeodata.php';
include_once dirname(__FILE__).'/classes/dpdparcelshop.php';

class DpdShipping extends CarrierModule
{
	private $config;

	/**
		* mandatory module functions
		*/
	public function __construct()
	{
		$this->config = new DpdConfig();	// Special configuration class to automate (un)install and form generation.
		$this->name = 'dpdshipping';
		$this->tab = 'shipping_logistics';
		$this->version = '0.3';
		$this->author = 'Michiel Van Gucht';
		$this->need_instance = 1;
		//$this->ps_versions_compliancy = array('min' => '1.6', 'max' => _PS_VERSION_);
		$this->bootstrap = true;
		
		parent::__construct();
		
		$this->displayName = $this->l('DPD Shipping');
		$this->description = $this->l('This module depends on the DPD Delicom webservices for ParcelShop location, label generation and tracking.');
		
		$this->confirmUninstall = $this->l('Are you sure you want to uninstall the DPD Shipping Module?');
		
		// Verify if all values (in the config) are set.
		if (self::isInstalled($this->name))
		{
			$warning = array();
			foreach ($this->config->getAllElementsFlat() as $config_element)
			{
				$variable_name = $config_element['name'];
				$user_readable_name = $config_element['user_readable_name'];
				if (!($value = Configuration::get($variable_name)) || $value == '')
					$warning[] = $this->l('No value for "'.$user_readable_name.'" provided');
			}
			
			if (!extension_loaded('soap'))
				$warning[] = $this->l('The PHP SOAP extension not installed/enabled on this server.');
				
				if (count($warning))
					$this->warning = implode(' , ',$warning); 
		}
	}
	
	public function install()
	{
		// Verify if multishop is active.
		if (Shop::isFeatureActive())
			Shop::setContext(Shop::CONTEXT_ALL); // If active select all shops to install new module
		
		if (!parent::install()
			|| !$this->registerHook('header')
			|| !$this->registerHook('extraCarrier')
			|| !$this->registerHook('actionCarrierProcess') 
			|| !$this->registerHook('updateCarrier') 
			|| !$this->registerHook('displayAdminOrderLeft') 
		)
			return false;
		
		// Initiate config fields.
		foreach ($this->config->getAllElementsFlat() as $config_element)
		{
			$variable_name = $config_element['name'];
			$default_value = isset($config_element['default_value']) ? $config_element['default_value'] : '';
			if (!Configuration::updateValue($variable_name, $default_value))
				return false;
		}
		
		if(!$this->createDpdShippingCarriers())
			return false;
		
		if(!DPDParcelShop::install())
			return false;
		
		return true;
	}
	
	public function uninstall()
	{
		if (!parent::uninstall()
			|| !$this->unregisterHook('header')
			|| !$this->unregisterHook('extraCarrier')
			|| !$this->unregisterHook('actionCarrierProcess')
			|| !$this->unregisterHook('updateCarrier') 
			|| !$this->unregisterHook('displayAdminOrderLeft') 
		)
			return false;
			
		// Remove config fields
		foreach ($this->config->getAllElementsFlat() as $config_element)
		{
			$variable_name = $config_element['name'];
			if (!Configuration::deleteByName($variable_name))
				return false;
		}
		
		$delivery_methods = new DpdDeliveryMethods();
		foreach ($delivery_methods->getAllMethods() as $delivery_method)
		{
			$carrier = new Carrier(Configuration::get($delivery_method['id_name']));
			
			if (!$carrier->delete() || !Configuration::deleteByName($delivery_method['id_name']))
				return false;
		}
		
		return true;
	}
	
	public function getContent()
	{
		$output = null;
		
		if (Tools::isSubmit('submit'.$this->name))
		{
			foreach ($this->config->getAllElementsFlat() as $config_element)
			{
				$variable_name = $config_element['name'];
				$user_readable_name = $config_element['user_readable_name'];
				
				$value = strval(Tools::getValue($variable_name));
				if (!$value 
					|| empty($value))
						$output .= $this->displayError($this->l('Invalid Configuration value ('.$user_readable_name.')'));
					else
						Configuration::updateValue($variable_name, $value);
			}
			if ($output == null)
				$output .= $this->displayConfirmation($this->l('Settings updated'));
		}
		return $output.$this->displayForm();
	}
	
	public function displayForm()
	{
		// Get default language
		$default_lang = (int)Configuration::get('PS_LANG_DEFAULT');
		
		$fields_config = $this->config->getAllElements();
		
		$fields_form = array();
		
		foreach ($fields_config as $group_key => $config_group)
		{
			$fields_form[$group_key]['form'] = array(
				'legend'	=> array(
					'title'	=> $this->l($config_group['name'])
				),
				'submit'	=> array(
					'title'	=> $this->l('Save'),
					'class'	=> 'button'
				)
			);
			foreach ($config_group['elements'] as $element)
			{
				$config = $element;
				$config['label'] = $this->l($element['user_readable_name']);
				
				if(!isset($element['type']))
					$config['type'] = 'text';
					
				$fields_form[$group_key]['form']['input'][] = $config;
			}
		}
		
		$helper = new HelperForm();
		
		// Module, token and currentIndex
		$helper->module = $this;
		$helper->name_controller = $this->name;
		$helper->token = Tools::getAdminTokenLite('AdminModules');
		$helper->currentIndex = AdminController::$currentIndex.'&configure='.$this->name;
		
		// Language
		$helper->default_form_language = $default_lang;
		$helper->allow_employee_form_lang = $default_lang;
		
		// Title and toolbar
		$helper->title = $this->displayName;
		$helper->show_toolbar = true;			// false -> remove toolbar
		$helper->toolbar_scroll = true;			// yes - > Toolbar is always visible on the top of the screen.
		$helper->submit_action = 'submit'.$this->name;
		$helper->toolbar_btn = array(
			'save' =>
				array(
					'desc' => $this->l('Save'),
					'href' => AdminController::$currentIndex.'&configure='.$this->name.'&save'.$this->name.'&token='.Tools::getAdminTokenLite('AdminModules'),
				),
			'back'	=> 
				array(
					'href'	=> AdminController::$currentIndex.'&token='.Tools::getAdminTokenLite('AdminModules'),
					'desc'	=> $this->l('Back to list')
				)
			);
		
		// Load current value
		foreach ($this->config->getAllElementsFlat() as $config_element)
		{
			$variable_name = $config_element['name'];
			$helper->fields_value[$variable_name] = Configuration::get($variable_name);
		}
		
		return $helper->generateForm($fields_form);
	}
		
	/**
		* This method is required because we are extending a CarrierModule 
		* Compute and return shipping price depending on ranges set in back-office
		* 
		* Case 1:
		*
		* @param ...
		* @return float
		*/
	public function getOrderShippingCost($params, $shipping_cost)
	{
		return 5.20;
	}
	/**
		* This method is required because we are extending a CarrierModule 
		* Compute and return shipping price without using the ranges
		* 
		* Case 1:
		*
		* @param ...
		* @return float
		*/
	public function getOrderShippingCostExternal($params)
	{
		return 5.20;
	}
	
	public function hookHeader($params)
	{
		$this->context->controller->addCSS($this->_path.'views/templates/front/css/parcelshoplocator.css');
		
		$this->context->controller->addJS($this->_path.'views/templates/front/js/parcelshoplocator.js');
		$this->context->controller->addJS($this->_path.'views/templates/front/js/dpdshipping.js');
		$this->context->controller->addJS('https://maps.googleapis.com/maps/api/js?libraries=places');
		
		//return true;
	}

	public function hookExtraCarrier($params)
	{
		if((int)($params['cart']->id_carrier) == (int)(Configuration::get('DPDSHIPPING_SHOP_DELIVERY_CARRIER_ID')))
		{
			$country = new Country();
			$country_iso = $country->getIsoById($params['address']->id_country);
			$this->context->smarty->assign(
				array(
				'module_path' => $this->_path,
				'dictionary_XML' => $this->_path.'translations/dictionary.xml',
				'selected_address' => $params['address']->address1.', '.$params['address']->postcode.' '.$params['address']->city,
				'country' => $country_iso
				)
			);
			return $this->display(__FILE__, 'carrier.tpl');
		}
	}
	
	public function hookActionCarrierProcess($params)
	{
		$tools = new Tools();
		$selected_parcelshop_id = $tools->getValue('dpd_shipping_shop_id');
		$selected_parcelshop_details = json_decode($tools->getValue('dpd_shipping_shop_details'));
		$cart_id = $params['cart']->id;
		
		$parcel_shop = new DPDParcelShop();
		$parcel_shop->add($cart_id, $selected_parcelshop_id, $selected_parcelshop_details);
		
	}
	
	public function hookDisplayAdminOrderLeft(&$params)
	{
		$orderCarrier = new Carrier($params['cart']->id_carrier);
		$parcelShopCarrier = new Carrier(Configuration::get('DPDSHIPPING_SHOP_DELIVERY_CARRIER_ID'));
		
		$parcelshop = new DPDParcelShop($params['cart']->id);
		
		if($parcelshop->id_parcelshop != 0)
		{
			$parcelshop = new DPDParcelShop($params['cart']->id);
			
			$params['smarty']->tpl_vars['addresses']->value['delivery']->id = -1;
			$params['smarty']->tpl_vars['addresses']->value['delivery']->id_country = 3;
			$params['smarty']->tpl_vars['addresses']->value['delivery']->alias = 'ParcelShop';
			$params['smarty']->tpl_vars['addresses']->value['delivery']->company = 'ParcelShop';
			$params['smarty']->tpl_vars['addresses']->value['delivery']->firstname = $parcelshop->shop_name;
			$params['smarty']->tpl_vars['addresses']->value['delivery']->lastname = '';
			$params['smarty']->tpl_vars['addresses']->value['delivery']->address1 = $parcelshop->shop_street . " " . $parcelshop->shop_houseno;
			$params['smarty']->tpl_vars['addresses']->value['delivery']->postcode = $parcelshop->shop_zipcode;
			$params['smarty']->tpl_vars['addresses']->value['delivery']->city = $parcelshop->shop_city;
			
			$newAddress = $params['smarty']->tpl_vars['customer_addresses']->value[0];
			
			$newAddress['id_address'] = '-1';
			$newAddress['alias'] = 'ParcelShop';
			$newAddress['id_country'] = Country::getIdByName(null, $parcelshop->shop_country);
			$newAddress['company'] = $parcelshop->shop_name;
			$newAddress['address1'] = $parcelshop->shop_street . " " . $parcelshop->shop_houseno;
			$newAddress['postcode'] = '1000';
			$newAddress['city'] = $parcelshop->shop_city;
			
			$params['smarty']->tpl_vars['customer_addresses']->value[] = $newAddress;
			
			$params['smarty']->tpl_vars['order']->value->id_address_delivery = -1;
		}

	}
	
	public function hookUpdateCarrier($params)
	{
		$delivery_methods = new DpdDeliveryMethods();
		foreach ($delivery_methods->getAllMethods() as $delivery_method)
		{
			if ((int)($params['id_carrier']) == (int)(Configuration::get($delivery_method['id_name'])))
				Configuration::updateValue($delivery_method['id_name'], (int)($params['carrier']->id));
		}
	}
	
	private function createDpdShippingCarriers()
	{
		$delivery_methods = new DpdDeliveryMethods();
		
		foreach ($delivery_methods->getAllMethods() as $delivery_method)
		{
			$carrier = new Carrier();
			$carrier->name = $delivery_method['name'];
			$carrier->id_tax_rules_group = 0;
			$carrier->id_zone = 1;
			$carrier->url = 'http://dpd.com/be';
			$carrier->active = true;
			$carrier->delete = 0;
			$carrier->shipping_handling = false;
			$carrier->range_behavior = 0;
			$carrier->is_module = true;
			$carrier->is_free = true;
			$carrier->shipping_external = false;
			$carrier->external_module_name = $this->name;
			$carrier->need_range = true;
			
			$languages = Language::getLanguages(true);
			foreach ($languages as $language) 
			{
				if (isset($delivery_method['delay'][$language['iso_code']]))
					$carrier->delay[$language['id_lang']] = $delivery_method['delay'][$language['iso_code']];
				else
					$carrier->delay[$language['id_lang']] = $delivery_method['delay']['default'];
			}
			
			if (!$carrier->add())
				return false;
			
			foreach ($languages as $language) 
			{
					$groups = Group::getGroups($language['id_lang']);
					$group_ids = array();
					foreach ($groups as $group)
						$group_ids[] = $group['id_group'];
					$carrier->setGroups($group_ids);
			}
			Configuration::updateValue($delivery_method['id_name'], (int)($carrier->id));
		}
	return true;
	}

}

class DpdConfig 
{
	private $config = array(
		array(
			'name'	=> 'Delis Credentials',
			'elements'	=> array(
				array(
					'name'	=> 'DPDSHIPPING_DELIS_ID',
					'user_readable_name'	=>	'DelisID',
					'required'	=> true
				),
				array(
					'type' => 'password',
					'name'	=> 'DPDSHIPPING_DELIS_PASSWORD',
					'user_readable_name'	=> 	'Password',
					'required'	=> true
				),
				array(
					'type' => 'radio',
					'name' 	=> 'DPDSHIPPING_LIVE_SERVER',
					'user_readable_name' => 'Live Server',
					'required' => true,
					'class' => 't',
					'is_bool' => true,
					'values' => array(
						array(
							'id' => 'active_on',
							'value' => 1,
							'label' => 'Yes'
						),
						array(
							'id' => 'active_off',
							'value' => 2,
							'label' => 'No',
						)
					)
				)
			)
		),
		array(
			'name'	=> 'Printer Settings',
			'elements'	=> array(
				array(
					'name'	=> 'DPDSHIPPING_OS_LABELTYPE',
					'user_readable_name'	=>	'Label Type',
					'default_value'	=> 'PDF',
					'required'	=> true
				),
				array(
					'name'	=> 'DPDSHIPPING_OS_LABELSIZE',
					'user_readable_name'	=>	'Label Size',
					'default_value'	=> 'A4',
					'required'	=> true
				),
			)
		)
	);

	public function getAllElementsFlat()
	{
		$result = array();
		
		foreach ($this->config as $config_group)
		{
			$result = array_merge($result, $config_group['elements']);
		}
		
		return $result;
	}
	
	public function getAllElements()
	{
		return $this->config;		
	}

}

class DpdDeliveryMethods
{
	private $config = array(
		array(
			'name' => 'DPD Home Delivery',
			'id_name' => 'DPDSHIPPING_HOME_DELIVERY_CARRIER_ID',
			'delay' => 
			array(
				'default' => 'Default 2Home message.',
				'en' => 'Get your parcel delivered at your place.'
			)
		),
		array(
			'name' => 'DPD ParcelShop Delivery',
			'id_name' => 'DPDSHIPPING_SHOP_DELIVERY_CARRIER_ID',
			'delay' => 
			array(
				'default' => 'Default ParcelShop message.',
				'en' => 'Pick your parcel up in a DPD ParcelShop on your way to or from work.'
			)
		)
	);
	
	public function getAllMethods()
	{
		return $this->config;		
	}
}