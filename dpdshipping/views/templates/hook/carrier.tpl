<!-- Carrier DpdShipping  -->
<div id="dpdLocatorContainer">
<input type="hidden" name="dpd_shipping_shop_id" id="dpd_shipping_shop_id">
<input type="hidden" name="dpd_shipping_shop_details" id="dpd_shipping_shop_details">
	<div id="chosenShop"></div>
</div>

<script type="text/javascript">
{literal}

	var dpdLocator = new DPD.locator({
		rootpath: '{/literal}{$module_path}{literal}',
		ajaxpath: '{/literal}{$base_dir}{literal}index.php?fc=module&module=dpdshipping&controller=parcelshoplocator',
		containerId: 'dpdLocatorContainer',
		fullscreen: false,
		width: '100%',
		height: '600px',
		filter: 'pick-up',
		country: '{/literal}{$country}{literal}',
		callback: 'dpdChosenShop',
		dictionaryXML: '{/literal}{$dictionary_XML}{literal}',
		language: '{/literal}{$lang_iso}{literal}_{/literal}{$country}{literal}'
	});
	
	dpdLocator.initialize();
	dpdLocator.showLocator('{/literal}{$selected_address}{literal}');

{/literal}
</script>