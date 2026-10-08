<?php
/**
 * Fika: search and share previews.
 *
 * Adds a meta description and Open Graph / Twitter tags to the shop pages, so a link pasted into WhatsApp,
 * Instagram or Facebook shows the candy photo, a title and a short line, and Google has a description.
 * Share picture: media 401 (wordpress/email-assets/fika-share-1200x630.jpg).
 */

const FIKA_SEO_IMAGE = 401;

function fika_seo_pages() {
	return array(
		'home'         => array( 'Fika · Swedish candy, delivered in Lebanon', 'Swedish pick & mix candy, delivered in Lebanon. Mix your own bag by weight or pick a Ready Mix, and pay cash on delivery.' ),
		'mix-your-own' => array( 'Mix your own · Fika', 'Build your own bag of Swedish candy: sweet, sour and gelatin-free picks, by weight. Delivered in Lebanon.' ),
		'ready-mix'    => array( 'Ready Mix · Fika', 'Ready-made bags of Swedish candy, mixed by us. Delivered in Lebanon, cash on delivery.' ),
		'about-us'     => array( 'About us · Fika', 'The story behind Fika, Swedish candy in Lebanon, and how ordering and delivery work.' ),
	);
}

add_action( 'wp_head', function () {
	if ( is_front_page() ) {
		$slug = 'home';
	} elseif ( is_page() ) {
		$slug = get_post_field( 'post_name', get_queried_object_id() );
	} else {
		return;
	}
	$pages = fika_seo_pages();
	if ( ! isset( $pages[ $slug ] ) ) {
		return;
	}
	list( $title, $desc ) = $pages[ $slug ];
	$url = 'home' === $slug ? home_url( '/' ) : get_permalink( get_queried_object_id() );
	$img = wp_get_attachment_image_src( FIKA_SEO_IMAGE, 'full' );
	$tags = array(
		array( 'name', 'description', $desc ),
		array( 'property', 'og:type', 'website' ),
		array( 'property', 'og:site_name', 'Fika' ),
		array( 'property', 'og:locale', 'en_US' ),
		array( 'property', 'og:title', $title ),
		array( 'property', 'og:description', $desc ),
		array( 'property', 'og:url', $url ),
		array( 'name', 'twitter:card', $img ? 'summary_large_image' : 'summary' ),
		array( 'name', 'twitter:title', $title ),
		array( 'name', 'twitter:description', $desc ),
	);
	if ( $img ) {
		$tags[] = array( 'property', 'og:image', $img[0] );
		$tags[] = array( 'property', 'og:image:width', $img[1] );
		$tags[] = array( 'property', 'og:image:height', $img[2] );
		$tags[] = array( 'property', 'og:image:alt', 'A bowl of colourful Swedish candy' );
		$tags[] = array( 'name', 'twitter:image', $img[0] );
	}
	foreach ( $tags as $t ) {
		printf( "<meta %s=\"%s\" content=\"%s\" />\n", $t[0], esc_attr( $t[1] ), esc_attr( $t[2] ) );
	}
}, 2 );
