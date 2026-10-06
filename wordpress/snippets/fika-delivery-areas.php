<?php
/**
 * Fika: delivery areas for Lebanon.
 * Adds the governorates as a required "Delivery area" dropdown at checkout, so delivery can be priced:
 * Beirut $5 (shipping zone "Beirut"), everywhere else $6 (zone "Lebanon (outside Beirut)").
 * Installed with the Code Snippets plugin.
 */

add_filter( 'woocommerce_states', function ( $states ) {
	$states['LB'] = array(
		'BA' => 'Beirut',
		'JL' => 'Mount Lebanon',
		'AS' => 'North Lebanon',
		'AK' => 'Akkar',
		'BI' => 'Bekaa',
		'BH' => 'Baalbek-Hermel',
		'JA' => 'South Lebanon',
		'NA' => 'Nabatieh',
	);
	return $states;
} );

add_filter( 'woocommerce_get_country_locale', function ( $locale ) {
	$locale['LB']['state'] = array(
		'label'    => 'Delivery area',
		'required' => true,
		'hidden'   => false,
	);
	return $locale;
}, 20 );
