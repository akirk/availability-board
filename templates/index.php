<?php
/**
 * The availability board: every product as a big tap-to-toggle availability
 * switch, grouped by category, with an instant search filter.
 */

if ( ! function_exists( 'wc_get_products' ) ) {
	?>
	<!DOCTYPE html>
	<html <?php wp_app_language_attributes(); ?>>
	<head>
		<meta charset="UTF-8">
		<meta name="viewport" content="width=device-width, initial-scale=1.0">
		<title><?php wp_app_title( 'Availability Board' ); ?></title>
		<?php wp_app_head(); ?>
	</head>
	<body>
		<?php wp_app_body_open(); ?>
		<main style="max-width:480px;margin:3rem auto;padding:0 1rem;text-align:center;">
			<h1><?php esc_html_e( 'Availability Board needs WooCommerce', 'availability-board' ); ?></h1>
			<p><?php esc_html_e( 'Install and activate WooCommerce, then reload this page.', 'availability-board' ); ?></p>
		</main>
		<?php wp_app_body_close(); ?>
	</body>
	</html>
	<?php
	return;
}

$products = wc_get_products(
	[
		'status'  => 'publish',
		'limit'   => -1,
		'orderby' => 'title',
		'order'   => 'ASC',
	]
);

$groups         = [];
$available_count = 0;

foreach ( $products as $product ) {
	$terms         = get_the_terms( $product->get_id(), 'product_cat' );
	$category_name = ( $terms && ! is_wp_error( $terms ) && ! empty( $terms ) ) ? $terms[0]->name : __( 'Other', 'availability-board' );

	if ( ! isset( $groups[ $category_name ] ) ) {
		$groups[ $category_name ] = [];
	}
	$groups[ $category_name ][] = $product;

	if ( 'instock' === $product->get_stock_status() ) {
		++$available_count;
	}
}

ksort( $groups, SORT_NATURAL | SORT_FLAG_CASE );
$total_count = count( $products );
?>
<!DOCTYPE html>
<html <?php wp_app_language_attributes(); ?>>
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?php wp_app_title( 'Availability Board' ); ?></title>
	<?php wp_app_head(); ?>
	<style>
		:root { color-scheme: light dark; }
		body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: var(--wp-app-color-background); color: var(--wp-app-color-text); }
		main { max-width: 640px; margin: 0 auto; padding: 1rem 1rem 4rem; }
		h1 { font-size: 1.4rem; margin: 1rem 0 0.25rem; }
		.summary { color: var(--wp-app-color-muted); margin: 0 0 1rem; }
		.search-form input[type="search"] { width: 100%; box-sizing: border-box; padding: 0.6rem 0.75rem; border-radius: 6px; border: 1px solid var(--wp-app-color-border); background: var(--wp-app-color-surface); color: var(--wp-app-color-text); font-size: 1rem; margin-bottom: 1.25rem; }
		.category { margin-bottom: 1.5rem; }
		.category h2 { font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.04em; color: var(--wp-app-color-muted); margin: 0 0 0.5rem; }
		.item-row { display: flex; align-items: center; gap: 0.75rem; background: var(--wp-app-color-surface); border-radius: 8px; padding: 0.8rem 1rem; margin-bottom: 0.5rem; }
		.item-row.is-unavailable { opacity: 0.6; }
		.item-main { flex: 1; min-width: 0; }
		.item-name { font-weight: 600; }
		.item-row.is-unavailable .item-name { text-decoration: line-through; }
		.item-price { color: var(--wp-app-color-muted); font-size: 0.85rem; }
		.item-state { font-size: 0.75rem; width: 4.5rem; text-align: right; color: var(--wp-app-color-muted); flex-shrink: 0; }
		.item-row.is-unavailable .item-state { color: #b23b3b; font-weight: 600; }
		.toggle { position: relative; display: inline-block; width: 48px; height: 28px; flex-shrink: 0; }
		.toggle input { opacity: 0; width: 0; height: 0; }
		.toggle-slider { position: absolute; inset: 0; background: var(--wp-app-color-border); border-radius: 999px; transition: background 0.2s; cursor: pointer; }
		.toggle-slider::before { content: ""; position: absolute; height: 22px; width: 22px; left: 3px; top: 3px; background: #fff; border-radius: 50%; transition: transform 0.2s; }
		.toggle input:checked + .toggle-slider { background: #2e7d32; }
		.toggle input:checked + .toggle-slider::before { transform: translateX(20px); }
		.toggle input:disabled + .toggle-slider { opacity: 0.5; cursor: default; }
		.empty-state { text-align: center; color: var(--wp-app-color-muted); padding: 3rem 1rem; }
	</style>
</head>
<body>
	<?php wp_app_body_open(); ?>

	<main>
		<h1><?php esc_html_e( 'Availability Board', 'availability-board' ); ?></h1>
		<p class="summary" id="board-summary">
			<?php
			echo esc_html(
				sprintf(
					/* translators: 1: available count, 2: total count */
					__( '%1$d of %2$d available', 'availability-board' ),
					$available_count,
					$total_count
				)
			);
			?>
		</p>

		<div class="search-form">
			<input type="search" id="board-search" placeholder="<?php esc_attr_e( 'Search products', 'availability-board' ); ?>">
		</div>

		<div id="board-list">
			<?php if ( empty( $products ) ) : ?>
				<p class="empty-state"><?php esc_html_e( 'No products found. Add some in WooCommerce first.', 'availability-board' ); ?></p>
			<?php else : ?>
				<?php foreach ( $groups as $category_name => $group_products ) : ?>
					<div class="category" data-category>
						<h2><?php echo esc_html( $category_name ); ?></h2>
						<?php foreach ( $group_products as $product ) : ?>
							<?php
							$is_available = 'instock' === $product->get_stock_status();
							$row_id       = 'item-' . $product->get_id();
							?>
							<div class="item-row<?php echo $is_available ? '' : ' is-unavailable'; ?>" id="<?php echo esc_attr( $row_id ); ?>" data-name="<?php echo esc_attr( mb_strtolower( $product->get_name() ) ); ?>">
								<div class="item-main">
									<div class="item-name"><?php echo esc_html( $product->get_name() ); ?></div>
									<div class="item-price"><?php echo wp_kses_post( $product->get_price_html() ); ?></div>
								</div>
								<span class="item-state"><?php echo $is_available ? esc_html__( 'Available', 'availability-board' ) : esc_html__( 'Unavailable', 'availability-board' ); ?></span>
								<label class="toggle">
									<input type="checkbox" class="availability-toggle" data-product-id="<?php echo esc_attr( $product->get_id() ); ?>" <?php checked( $is_available ); ?>>
									<span class="toggle-slider"></span>
								</label>
							</div>
						<?php endforeach; ?>
					</div>
				<?php endforeach; ?>
			<?php endif; ?>
		</div>
	</main>

	<script>
	var boardConfig = {
		restUrl: <?php echo wp_json_encode( esc_url_raw( rest_url( \AvailabilityBoard\App::REST_NAMESPACE ) ) ); ?>,
		nonce: <?php echo wp_json_encode( wp_create_nonce( 'wp_rest' ) ); ?>,
		availableLabel: <?php echo wp_json_encode( __( 'Available', 'availability-board' ) ); ?>,
		unavailableLabel: <?php echo wp_json_encode( __( 'Unavailable', 'availability-board' ) ); ?>
	};

	function updateSummary() {
		var toggles = document.querySelectorAll( '.availability-toggle' );
		var available = 0;
		toggles.forEach( function ( toggle ) {
			if ( toggle.checked ) {
				available++;
			}
		} );
		document.getElementById( 'board-summary' ).textContent = available + ' of ' + toggles.length + ' available';
	}

	document.addEventListener( 'change', function ( event ) {
		var toggle = event.target;
		if ( ! toggle.classList || ! toggle.classList.contains( 'availability-toggle' ) ) {
			return;
		}

		var row         = toggle.closest( '.item-row' );
		var stateLabel  = row.querySelector( '.item-state' );
		var id          = toggle.dataset.productId;
		var wantInStock = toggle.checked;
		toggle.disabled = true;

		fetch( boardConfig.restUrl + '/products/' + id + '/availability', {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': boardConfig.nonce
			},
			body: JSON.stringify( { in_stock: wantInStock } )
		} )
			.then( function ( response ) {
				return response.json().then( function ( data ) {
					return { ok: response.ok, data: data };
				} );
			} )
			.then( function ( result ) {
				toggle.disabled = false;
				if ( ! result.ok ) {
					toggle.checked = ! wantInStock;
					alert( result.data.message || 'Could not update this item.' );
					return;
				}
				row.classList.toggle( 'is-unavailable', ! wantInStock );
				stateLabel.textContent = wantInStock ? boardConfig.availableLabel : boardConfig.unavailableLabel;
				updateSummary();
			} )
			.catch( function () {
				toggle.disabled = false;
				toggle.checked = ! wantInStock;
				alert( 'Network error. Please try again.' );
			} );
	} );

	document.getElementById( 'board-search' ).addEventListener( 'input', function ( event ) {
		var needle = event.target.value.trim().toLowerCase();
		document.querySelectorAll( '.item-row' ).forEach( function ( row ) {
			row.style.display = row.dataset.name.indexOf( needle ) === -1 ? 'none' : '';
		} );
		document.querySelectorAll( '[data-category]' ).forEach( function ( category ) {
			var anyVisible = Array.prototype.some.call(
				category.querySelectorAll( '.item-row' ),
				function ( row ) { return row.style.display !== 'none'; }
			);
			category.style.display = anyVisible ? '' : 'none';
		} );
	} );
	</script>

	<?php wp_app_body_close(); ?>
</body>
</html>
