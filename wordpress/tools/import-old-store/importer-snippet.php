// TEMP: import orders from the old Website Builder store (delete after use). POST JSON list; ?dry=1 checks only.
add_action( 'rest_api_init', function () {
	register_rest_route( 'fika/v1', '/tmp-import', array( 'methods' => 'POST', 'permission_callback' => function () { return current_user_can( 'manage_options' ); }, 'callback' => function ( $req ) {
		$list = json_decode( $req->get_body(), true );
		if ( ! is_array( $list ) ) { return new WP_Error( 'bad', 'bad body', array( 'status' => 400 ) ); }
		$dry = (bool) $req->get_param( 'dry' );
		add_filter( 'pre_wp_mail', '__return_false', 9999 ); // no emails of any kind from this request
		foreach ( WC()->mailer()->get_emails() as $em ) { add_filter( 'woocommerce_email_enabled_' . $em->id, '__return_false', 9999 ); }
		$out = array( 'created' => 0, 'updated' => 0, 'same' => 0, 'errors' => array() );
		foreach ( $list as $r ) {
			try {
				$have = wc_get_orders( array( 'limit' => 1, 'return' => 'ids', 'type' => 'shop_order', 'status' => 'any', 'meta_key' => '_fika_old_order', 'meta_value' => (string) $r['num'] ) );
				if ( $have ) {
					$o = wc_get_order( $have[0] );
					if ( $o->get_status() !== $r['status'] ) {
						if ( ! $dry ) { if ( 'completed' === $r['status'] ) { $o->set_date_completed( time() ); } $o->update_status( $r['status'], 'Status updated from the old store.' ); }
						$out['updated']++;
					} else { $out['same']++; }
					continue;
				}
				foreach ( $r['items'] as $it ) { if ( ! wc_get_product( $it['id'] ) ) { throw new Exception( 'product ' . $it['id'] ); } }
				if ( $dry ) { $out['created']++; continue; }
				$o = new WC_Order();
				$o->set_created_via( 'fika-import' );
				$o->set_currency( 'USD' );
				$o->set_customer_id( 0 );
				$o->set_prices_include_tax( false );
				foreach ( array( 'billing', 'shipping' ) as $t ) {
					$o->{"set_{$t}_first_name"}( $r['first'] ); $o->{"set_{$t}_last_name"}( $r['last'] );
					$o->{"set_{$t}_address_1"}( $r['a1'] ); $o->{"set_{$t}_address_2"}( $r['a2'] );
					$o->{"set_{$t}_city"}( $r['city'] ); $o->{"set_{$t}_state"}( $r['area'] ); $o->{"set_{$t}_country"}( 'LB' );
					$o->{"set_{$t}_phone"}( $r['phone'] );
				}
				$o->set_billing_email( $r['email'] );
				// the discount is spread over the lines, as WooCommerce does, so reports show what was really paid
				$left = round( (float) $r['disc'], 2 ); $n = count( $r['items'] );
				foreach ( $r['items'] as $k => $it ) {
					$p    = wc_get_product( $it['id'] );
					$line = (float) $it['line'];
					$d    = $k === $n - 1 ? $left : round( $r['sub'] > 0 ? $r['disc'] * $line / $r['sub'] : 0, 2 );
					$left = round( $left - $d, 2 );
					$item = new WC_Order_Item_Product();
					$item->set_product( $p ); $item->set_name( $p->get_name() ); $item->set_quantity( (int) $it['qty'] );
					$item->set_subtotal( $line ); $item->set_total( max( 0, $line - $d ) );
					$o->add_item( $item );
				}
				$sh = new WC_Order_Item_Shipping();
				$sh->set_method_title( 'BA' === $r['area'] ? 'Delivery in Beirut' : 'Delivery outside Beirut' );
				$sh->set_method_id( 'flat_rate' ); $sh->set_total( (float) $r['ship'] );
				$o->add_item( $sh );
				if ( '' !== $r['code'] ) {
					$c = new WC_Order_Item_Coupon(); $c->set_code( strtolower( $r['code'] ) ); $c->set_discount( (float) $r['disc'] ); $o->add_item( $c );
				}
				$o->set_discount_total( (float) $r['disc'] ); $o->set_shipping_total( (float) $r['ship'] ); $o->set_cart_tax( 0 ); $o->set_shipping_tax( 0 );
				$o->set_total( (float) $r['total'] );
				if ( 'Whish' === $r['pay'] ) { $o->set_payment_method( 'whish' ); $o->set_payment_method_title( 'Whish' ); }
				else { $o->set_payment_method( 'cod' ); $o->set_payment_method_title( 'Cash on delivery' ); }
				$o->set_date_created( (int) $r['ts'] );
				if ( 'completed' === $r['status'] ) { $o->set_date_completed( (int) $r['ts'] ); }
				if ( $r['paid'] ) { $o->set_date_paid( (int) $r['ts'] ); }
				$o->update_meta_data( '_fika_old_order', (string) $r['num'] );
				$o->set_status( $r['status'] );
				$o->save();
				$o->add_order_note( 'Imported from the old Fika store (Website Builder), order #' . $r['num'] . '.' );
				$out['created']++;
			} catch ( \Throwable $e ) {
				$out['errors'][] = $r['num'] . ': ' . $e->getMessage();
			}
		}
		return $out;
	} ) );
} );
