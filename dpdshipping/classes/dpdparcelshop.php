<?php
if (!defined('_PS_VERSION_'))
 exit;

function isDPDParcelShop($mixed){
	return (get_class($mixed) == 'DPDParcelShop');
}

class DPDParcelShop
{
	private $table;
	
	public $id_parcelshop;
	public $shop_name;
	public $shop_street;
	public $shop_houseno;
	public $shop_country;
	public $shop_zipcode;
	public $shop_city;
	
	
	public function __construct(){
		$this->table = 'cart_dpdparcelshop';
	}
	
	public static function install()
	{
		Db::getInstance()->execute("CREATE TABLE IF NOT EXISTS `"._DB_PREFIX_."cart_dpdparcelshop` (
			`id_cart` int(10) unsigned DEFAULT NULL,
			`id_parcelshop` int(6) unsigned DEFAULT NULL,
			`shop_name` varchar(100) DEFAULT NULL,
			`shop_street` varchar(100) DEFAULT NULL,
			`shop_houseno` varchar(10) DEFAULT NULL,
			`shop_country` varchar(2) DEFAULT NULL,
			`shop_zipcode` varchar(10) DEFAULT NULL,
			`shop_city` varchar(50) DEFAULT NULL,
			`date_add` datetime DEFAULT CURRENT_TIMESTAMP,
			`date_update`datetime DEFAULT CURRENT_TIMESTAMP
			PRIMARY KEY (`id_cart`)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8;");
			
		return true;
	}
	
	public function add($id_cart, $shop_id, $shop_details = false)
	{
		if($shop_details)
		{
			return(Db::getInstance()->execute("INSERT INTO "._DB_PREFIX_.$this->table." (`id_cart`,`id_parcelshop`,`shop_name`,`shop_street`,`shop_houseno`,`shop_country`,`shop_zipcode`,`shop_city`) 
				VALUES (".(int)$id_cart.",".(int)$shop_id.",'".(string)$shop_details->name."','".(string)$shop_details->street."','".(string)$shop_details->houseNo."','".(string)$shop_details->country."','".(string)$shop_details->zipCode."','".(string)$shop_details->city."')
				ON DUPLICATE KEY UPDATE
					`id_parcelshop` =  ".(int)$shop_id.",
					`shop_name` = '".(string)$shop_details->name."',
					`shop_street` = '".(string)$shop_details->street."',
					`shop_houseno` = '".(string)$shop_details->houseNo."',
					`shop_country` = '".(string)$shop_details->country."',
					`shop_zipcode` = '".(string)$shop_details->zipCode."',
					`shop_city` = '".(string)$shop_details->city."',
					`date_update`= NOW();"));
		} else {
			return(Db::getInstance()->execute("INSERT INTO "._DB_PREFIX_.$this->table." (`id_cart`,`id_parcelshop`) 
				VALUES (".(int)$id_cart.",".(int)$shop_id.")
				ON DUPLICATE KEY UPDATE
					`id_parcelshop` =  ".(int)$shop_id.",
					`shop_name` = '',
					`shop_street` = '',
					`shop_houseno` = '',
					`shop_country` = '',
					`shop_zipcode` = '',
					`shop_city` = '',
					`date_update`= NOW();"));
		}
	}
	
	public function lookup($id_cart)
	{
		$sql = 'SELECT * FROM '._DB_PREFIX_.$this->table.' WHERE `id_cart` = '.$id_cart.' ORDER BY `date_add`;';
		if ($row = Db::getInstance()->getRow($sql))
		{
			$this->id_parcelshop = $row['id_parcelshop'];
			$this->shop_name = $row['shop_name'];
			$this->shop_street = $row['shop_street'];
			$this->shop_houseno = $row['shop_houseno'];
			$this->shop_country = $row['shop_country'];
			$this->shop_zipcode = $row['shop_zipcode'];
			$this->shop_city = $row['shop_city'];
		}
	}
}