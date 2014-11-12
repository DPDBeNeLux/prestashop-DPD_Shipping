function dpdChosenShop(shopID) {
	var shop = dpdLocator.getShopInfo(shopID);
	
	document.getElementById('dpd_shipping_shop_id').value = shopID;
	document.getElementById('dpd_shipping_shop_details').value = JSON.stringify(shop);
	
	dpdLocator.hideLocator();

	var objContainer = document.getElementById('chosenShop');
	objContainer.innerHTML = '<p>You have chosen: <strong>' + shop.name + '</strong>'+
		' <br>Located at: ' + shop.street + ' ' + shop.houseNo + ', ' + shop.zipCode + ' ' + shop.city + '</p>'+
		'<a href="#" onclick="javascript:dpdLocator.showLocator();return false;">Click here to alter your choice</a>';
}
