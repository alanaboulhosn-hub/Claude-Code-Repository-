<?php
/**
 * Fika: delivery areas for Lebanon.
 * Adds a required "Delivery area" dropdown at checkout (Inside Beirut / Outside Beirut), so delivery can be priced:
 * Beirut $5 (shipping zone "Beirut"), everywhere else $6 (zone "Lebanon (outside Beirut)").
 * Installed with the Code Snippets plugin.
 */

add_filter( 'woocommerce_states', function ( $states ) {
	$states['LB'] = array(
		'BA' => 'Inside Beirut',
		'OB' => 'Outside Beirut',
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
