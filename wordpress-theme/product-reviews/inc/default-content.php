<?php
/**
 * Fresh-install defaults and bundled fallback catalog.
 *
 * A new WordPress install should never look empty. This file seeds a small set
 * of editable review posts and provides local fallback product rows for search
 * and source failover when live product-data credentials are not configured yet.
 *
 * @package ProductReviews
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Default product catalog grouped by shopper intent.
 *
 * @return array<string,array<string,mixed>>
 */
function pr_default_product_catalog(): array {
	return array(
		'coffee-beans' => array(
			'title'    => 'The 7 Best Coffee Beans for Fresh Home Brewing',
			'keyword'  => 'coffee beans',
			'category' => 'Home & Kitchen',
			'intro'    => 'We selected these coffee beans to cover the most common buying intents: smooth daily coffee, richer espresso-style cups, budget-friendly bulk bags, and low-acid options for sensitive drinkers.',
			'tldr'     => 'For most homes, a balanced medium roast is the safest starting point. If you prefer bold espresso-style coffee, pick a darker roast; if you drink several cups daily, prioritize larger bags and recent roast dates.',
			'buyers'   => array(
				'Choose whole beans if you own a grinder; they stay fresher longer than pre-ground coffee.',
				'Medium roasts are easiest for drip machines, pour-over, and casual everyday brewing.',
				'Dark roasts suit milk drinks and stronger espresso-style cups, but can taste smoky if over-extracted.',
				'Check roast date, bag size, and whether the beans match your preferred brew method.',
			),
			'faqs'     => array(
				array( 'q' => 'Are whole coffee beans better than ground coffee?', 'a' => 'Whole beans usually taste fresher because less surface area is exposed to oxygen. Ground coffee is more convenient but loses aroma faster after opening.' ),
				array( 'q' => 'What roast should most people buy first?', 'a' => 'A medium roast is the best first choice for most shoppers because it balances flavor, acidity, and brew-method flexibility.' ),
			),
			'products' => array(
				array( 'rank' => 1, 'asin' => 'B0002E2GQU', 'title' => 'Lavazza Super Crema Whole Bean Coffee Blend', 'brand' => 'Lavazza', 'image' => 'https://m.media-amazon.com/images/I/81h2gWPTYJL._AC_SL1500_.jpg', 'price' => 22.99, 'rating' => 4.6, 'review_count' => 91700, 'badge' => 'editors_choice', 'why' => 'A versatile whole-bean blend for shoppers who want a smooth, low-fuss daily coffee that also works well in espresso machines. It is a practical first pick because it balances mild sweetness, body, and broad brewing compatibility.', 'pros' => array( 'Smooth daily flavor profile', 'Works for espresso and drip', 'Large review base' ), 'cons' => array( 'Not ideal for very light-roast fans', 'Bag size may be large for occasional drinkers' ), 'features' => array( 'Whole bean', 'Medium roast', 'Espresso-friendly blend' ) ),
				array( 'rank' => 2, 'asin' => 'B00I08JAYG', 'title' => 'Amazon Fresh Colombia Whole Bean Coffee', 'brand' => 'Amazon Fresh', 'image' => 'https://m.media-amazon.com/images/I/81xn2w37xHL._AC_SL1500_.jpg', 'price' => 15.49, 'rating' => 4.4, 'review_count' => 51000, 'badge' => 'best_value', 'why' => 'A strong value choice for everyday brewing when you want a recognizable single-origin style without paying specialty-shop prices. It suits drip machines, French press, and regular morning routines.', 'pros' => array( 'Good value for daily use', 'Approachable medium roast', 'Easy to reorder' ), 'cons' => array( 'Less complex than premium roasters', 'Packaging freshness can vary' ), 'features' => array( 'Whole bean', 'Colombia origin', 'Medium roast' ) ),
				array( 'rank' => 3, 'asin' => 'B00L5K3P8U', 'title' => 'Kicking Horse Coffee, Kick Ass Dark Roast Whole Bean', 'brand' => 'Kicking Horse Coffee', 'image' => 'https://m.media-amazon.com/images/I/81Iq3VkTz-L._AC_SL1500_.jpg', 'price' => 11.99, 'rating' => 4.5, 'review_count' => 22000, 'badge' => 'budget', 'why' => 'A bold dark roast for buyers who like stronger cups and milk-based drinks. It is best for people who want more roast intensity and less delicate acidity.', 'pros' => array( 'Bold flavor', 'Good for milk drinks', 'Organic option' ), 'cons' => array( 'May taste too dark for some', 'Smaller bag size' ), 'features' => array( 'Whole bean', 'Dark roast', 'Organic' ) ),
				array( 'rank' => 4, 'asin' => 'B00P0ZMWEC', 'title' => 'Death Wish Coffee Organic Whole Bean Dark Roast', 'brand' => 'Death Wish Coffee', 'image' => 'https://m.media-amazon.com/images/I/71M9i8r5H3L._AC_SL1500_.jpg', 'price' => 19.99, 'rating' => 4.6, 'review_count' => 34000, 'badge' => 'strongest', 'why' => 'Best for shoppers who want a high-caffeine, intense dark roast rather than a mild breakfast blend.', 'pros' => array( 'Very bold cup', 'Organic beans', 'Good for strong-coffee fans' ), 'cons' => array( 'Too intense for some', 'Not a subtle roast' ), 'features' => array( 'Whole bean', 'Dark roast', 'High caffeine' ) ),
				array( 'rank' => 5, 'asin' => 'B001E5E24A', 'title' => 'Peet’s Coffee Major Dickason’s Blend Whole Bean', 'brand' => 'Peet’s Coffee', 'image' => 'https://m.media-amazon.com/images/I/81mw2B62XQL._AC_SL1500_.jpg', 'price' => 13.98, 'rating' => 4.7, 'review_count' => 52000, 'badge' => 'classic', 'why' => 'A familiar dark blend for buyers who want a rich, dependable cup from a long-running coffee brand.', 'pros' => array( 'Rich classic flavor', 'Widely available', 'Good aroma' ), 'cons' => array( 'Darker than some expect', 'Best when consumed fresh' ), 'features' => array( 'Whole bean', 'Dark roast', 'Multi-origin blend' ) ),
				array( 'rank' => 6, 'asin' => 'B00I08JKIY', 'title' => 'Amazon Fresh Organic Fair Trade Peru Whole Bean Coffee', 'brand' => 'Amazon Fresh', 'image' => 'https://m.media-amazon.com/images/I/81ubN9LSbFL._AC_SL1500_.jpg', 'price' => 18.99, 'rating' => 4.4, 'review_count' => 18000, 'badge' => 'organic_value', 'why' => 'A practical pick for shoppers who prefer organic and fair-trade labeling while staying in an accessible price tier.', 'pros' => array( 'Organic and fair trade', 'Good daily value', 'Medium roast balance' ), 'cons' => array( 'Not specialty-roaster complexity', 'Freshness varies by seller' ), 'features' => array( 'Whole bean', 'Organic', 'Fair trade' ) ),
				array( 'rank' => 7, 'asin' => 'B00HSRN3L4', 'title' => 'Stone Street Cold Brew Coffee Whole Bean', 'brand' => 'Stone Street', 'image' => 'https://m.media-amazon.com/images/I/81vNPeEpmgL._AC_SL1500_.jpg', 'price' => 14.99, 'rating' => 4.5, 'review_count' => 12600, 'badge' => 'cold_brew', 'why' => 'A focused option for people making cold brew at home who want a low-acid, coarse-grind-friendly bean profile.', 'pros' => array( 'Good for cold brew', 'Smooth low-acid profile', 'Strong value' ), 'cons' => array( 'Less versatile for pour-over', 'Flavor skews bold' ), 'features' => array( 'Whole bean', 'Cold brew focused', 'Dark roast' ) ),
			),
		),
		'robot-vacuums' => array(
			'title'    => 'The 7 Best Robot Vacuums for Cleaner Floors',
			'keyword'  => 'robot vacuums',
			'category' => 'Home & Kitchen',
			'intro'    => 'Robot vacuum shoppers usually need one of three things: a reliable starter model, stronger suction for pet hair, or a self-emptying model that reduces daily maintenance. These starter picks show how the review layout works and can be edited later.',
			'tldr'     => 'Most buyers should start with a robot vacuum that has dependable navigation, strong debris pickup, and an easy-to-clean dust bin. Pet owners should prioritize suction and brush design over app extras.',
			'buyers'   => array( 'Map-based navigation is worth paying for in larger homes.', 'Self-empty docks save time but increase cost and replacement-bag needs.', 'Pet homes should prioritize tangle-resistant brushes and high suction.', 'Check furniture clearance before buying.' ),
			'faqs'     => array(
				array( 'q' => 'Do robot vacuums replace a full-size vacuum?', 'a' => 'They are best for maintenance cleaning. Most homes still need a regular vacuum for stairs, upholstery, and deep cleaning.' ),
				array( 'q' => 'Is a self-empty dock worth it?', 'a' => 'It is worth it if you have pets, allergies, or large floor areas; otherwise a standard dock can be enough.' ),
			),
			'products' => array(
				array( 'rank' => 1, 'asin' => 'B08SP5GYJP', 'title' => 'iRobot Roomba 694 Robot Vacuum', 'brand' => 'iRobot', 'image' => 'https://m.media-amazon.com/images/I/71PLfaYtQzL._AC_SL1500_.jpg', 'price' => 179.99, 'rating' => 4.3, 'review_count' => 29100, 'badge' => 'editors_choice', 'why' => 'A practical starter robot vacuum from a well-known brand, best for shoppers who want app control, dependable daily cleaning, and a simple maintenance routine without jumping into premium dock systems.', 'pros' => array( 'Beginner-friendly app', 'Good brand support', 'Works for daily maintenance' ), 'cons' => array( 'No self-empty dock', 'Navigation is basic' ), 'features' => array( 'Wi-Fi connected', 'Works with voice assistants', 'Multi-surface cleaning' ) ),
				array( 'rank' => 2, 'asin' => 'B09NM549V6', 'title' => 'Shark AI Ultra Robot Vacuum with Self-Empty Base', 'brand' => 'Shark', 'image' => 'https://m.media-amazon.com/images/I/71Tn0lWc+gL._AC_SL1500_.jpg', 'price' => 299.99, 'rating' => 4.2, 'review_count' => 19800, 'badge' => 'premium', 'why' => 'A stronger option for buyers who want less hands-on emptying and better coverage in busy homes. The self-empty base is especially useful for pet hair and frequent cleaning schedules.', 'pros' => array( 'Self-empty base', 'Good for busy homes', 'Stronger feature set' ), 'cons' => array( 'Takes more floor space', 'Higher replacement cost' ), 'features' => array( 'Self-empty base', 'Mapping', 'Pet hair pickup' ) ),
				array( 'rank' => 3, 'asin' => 'B07R295MLS', 'title' => 'Eufy BoostIQ RoboVac 11S MAX Robot Vacuum Cleaner', 'brand' => 'eufy', 'image' => 'https://m.media-amazon.com/images/I/61mpMH5TzkL._AC_SL1500_.jpg', 'price' => 139.99, 'rating' => 4.4, 'review_count' => 46700, 'badge' => 'best_value', 'why' => 'A slim, value-focused robot vacuum for apartments and hard floors. It is a good fit if you want simple scheduled cleaning and do not need advanced mapping.', 'pros' => array( 'Slim design', 'Strong value', 'Simple controls' ), 'cons' => array( 'No room mapping', 'Manual emptying required' ), 'features' => array( 'Slim body', 'BoostIQ suction', 'Quiet operation' ) ),
				array( 'rank' => 4, 'asin' => 'B0BRT2W2J1', 'title' => 'Roborock Q5 Robot Vacuum with Strong Suction', 'brand' => 'Roborock', 'image' => 'https://m.media-amazon.com/images/I/61A7u5NYTML._AC_SL1500_.jpg', 'price' => 219.99, 'rating' => 4.4, 'review_count' => 8900, 'badge' => 'mapping', 'why' => 'A smart pick for homes that need more organized room coverage and app-based mapping without moving into the highest price tiers.', 'pros' => array( 'Reliable mapping', 'Strong suction', 'Good app controls' ), 'cons' => array( 'No auto-empty dock', 'Mop features are limited' ), 'features' => array( 'LiDAR navigation', 'App mapping', 'Strong suction' ) ),
				array( 'rank' => 5, 'asin' => 'B0C4N4VG8M', 'title' => 'Shark Matrix Plus 2-in-1 Robot Vacuum and Mop', 'brand' => 'Shark', 'image' => 'https://m.media-amazon.com/images/I/71An7mKoERL._AC_SL1500_.jpg', 'price' => 399.99, 'rating' => 4.2, 'review_count' => 7200, 'badge' => 'vacuum_mop', 'why' => 'A useful choice for shoppers who want both vacuuming and light mopping from one robot for hard-floor areas.', 'pros' => array( 'Vacuum and mop combo', 'Self-empty option', 'Good for hard floors' ), 'cons' => array( 'Mopping is maintenance-level', 'Higher upfront cost' ), 'features' => array( '2-in-1 cleaning', 'Matrix cleaning', 'Self-empty base' ) ),
				array( 'rank' => 6, 'asin' => 'B0C6X9QJ9K', 'title' => 'iRobot Roomba Combo i5 Robot Vacuum and Mop', 'brand' => 'iRobot', 'image' => 'https://m.media-amazon.com/images/I/61hZyqJ5RDL._AC_SL1500_.jpg', 'price' => 249.99, 'rating' => 4.1, 'review_count' => 6100, 'badge' => 'combo', 'why' => 'Best for iRobot shoppers who want a recognizable ecosystem and basic mop capability in one device.', 'pros' => array( 'iRobot ecosystem', 'Vacuum and mop bin', 'Good app experience' ), 'cons' => array( 'Mop is not deep-cleaning', 'Accessories add cost' ), 'features' => array( 'Vacuum and mop', 'Smart mapping', 'Voice assistant support' ) ),
				array( 'rank' => 7, 'asin' => 'B0B5DZWZT2', 'title' => 'Lefant M210 Pro Robot Vacuum Cleaner', 'brand' => 'Lefant', 'image' => 'https://m.media-amazon.com/images/I/61ZfQ3XxeML._AC_SL1500_.jpg', 'price' => 99.99, 'rating' => 4.2, 'review_count' => 17400, 'badge' => 'budget', 'why' => 'A budget-friendly robot vacuum for smaller spaces, dorms, and simple hard-floor maintenance cleaning.', 'pros' => array( 'Low price tier', 'Compact body', 'Good for small spaces' ), 'cons' => array( 'Basic navigation', 'Not ideal for thick carpet' ), 'features' => array( 'Slim design', 'App control', 'Scheduled cleaning' ) ),
			),
		),
		'wireless-earbuds' => array(
			'title'    => 'The 7 Best Wireless Earbuds for Calls, Music, and Travel',
			'keyword'  => 'wireless earbuds',
			'category' => 'Electronics',
			'intro'    => 'Wireless earbud buyers should compare fit, battery life, microphone quality, and noise control before choosing. These default picks cover common intents from everyday iPhone use to affordable gym earbuds.',
			'tldr'     => 'The best earbuds for most shoppers are the ones that fit securely and match your phone ecosystem. Noise cancellation matters for travel, while water resistance matters more for workouts.',
			'buyers'   => array( 'Pick earbuds that match your phone ecosystem for easier pairing.', 'Prioritize ANC for travel and commuting.', 'For workouts, check IP water-resistance ratings and secure fit.', 'Battery life should include both earbuds and charging case.' ),
			'faqs'     => array(
				array( 'q' => 'Is active noise cancellation worth it?', 'a' => 'It is worth it for commuting, flights, and noisy offices. For gym-only use, fit and water resistance may matter more.' ),
				array( 'q' => 'How long should earbud batteries last?', 'a' => 'Most good earbuds offer several hours per charge and extra cycles from the case. Heavy ANC use reduces runtime.' ),
			),
			'products' => array(
				array( 'rank' => 1, 'asin' => 'B0D1XD1ZV3', 'title' => 'Apple AirPods Pro 2 Wireless Earbuds', 'brand' => 'Apple', 'image' => 'https://m.media-amazon.com/images/I/61f1YfTkTDL._AC_SL1500_.jpg', 'price' => 189.99, 'rating' => 4.7, 'review_count' => 18600, 'badge' => 'editors_choice', 'why' => 'A strong overall choice for iPhone users who care about noise cancellation, seamless pairing, and everyday call quality. They make the most sense when you already use Apple devices.', 'pros' => array( 'Excellent Apple integration', 'Strong ANC', 'Compact case' ), 'cons' => array( 'Best value only for Apple users', 'Premium price tier' ), 'features' => array( 'ANC', 'Transparency mode', 'USB-C case' ) ),
				array( 'rank' => 2, 'asin' => 'B0BYPFNW6T', 'title' => 'Soundcore by Anker Space A40 Adaptive ANC Earbuds', 'brand' => 'Soundcore', 'image' => 'https://m.media-amazon.com/images/I/61i+KM5RGGL._AC_SL1500_.jpg', 'price' => 59.99, 'rating' => 4.4, 'review_count' => 21400, 'badge' => 'best_value', 'why' => 'A value-focused option for shoppers who want active noise cancellation and long battery life without paying flagship prices. It works well across Android and iPhone.', 'pros' => array( 'Strong value', 'ANC included', 'Long case battery' ), 'cons' => array( 'Call quality is not flagship-level', 'Fit depends on ear tips' ), 'features' => array( 'Adaptive ANC', 'Multipoint', 'Wireless charging' ) ),
				array( 'rank' => 3, 'asin' => 'B0B44F1GGK', 'title' => 'JBL Vibe Beam True Wireless Earbuds', 'brand' => 'JBL', 'image' => 'https://m.media-amazon.com/images/I/61JxCwQjUaL._AC_SL1500_.jpg', 'price' => 39.95, 'rating' => 4.3, 'review_count' => 23000, 'badge' => 'budget', 'why' => 'A straightforward budget pick for casual listening, gym bags, or backup earbuds. Choose these if low cost and a known audio brand matter more than advanced ANC.', 'pros' => array( 'Affordable', 'Recognizable brand', 'Good casual-use pick' ), 'cons' => array( 'No premium ANC', 'Basic feature set' ), 'features' => array( 'True wireless', 'Water resistant', 'Charging case' ) ),
				array( 'rank' => 4, 'asin' => 'B0BYPFTL4G', 'title' => 'Soundcore by Anker P20i True Wireless Earbuds', 'brand' => 'Soundcore', 'image' => 'https://m.media-amazon.com/images/I/61VbKHdE0rL._AC_SL1500_.jpg', 'price' => 24.99, 'rating' => 4.5, 'review_count' => 61000, 'badge' => 'ultra_budget', 'why' => 'A very affordable everyday pair for backup use, students, and casual listeners who do not need active noise cancellation.', 'pros' => array( 'Very low cost', 'Long case battery', 'Custom EQ app' ), 'cons' => array( 'No ANC', 'Basic microphones' ), 'features' => array( 'True wireless', 'App EQ', 'Compact case' ) ),
				array( 'rank' => 5, 'asin' => 'B0CHWRXH8B', 'title' => 'Samsung Galaxy Buds FE True Wireless Earbuds', 'brand' => 'Samsung', 'image' => 'https://m.media-amazon.com/images/I/61q+Q6YAFRL._AC_SL1500_.jpg', 'price' => 69.99, 'rating' => 4.4, 'review_count' => 10500, 'badge' => 'android', 'why' => 'A good Android-leaning choice for Galaxy phone owners who want ANC, easy pairing, and a secure wing-tip fit.', 'pros' => array( 'Good for Samsung phones', 'ANC included', 'Secure fit' ), 'cons' => array( 'Less seamless on iPhone', 'No wireless charging' ), 'features' => array( 'ANC', 'Galaxy ecosystem', 'Wing-tip fit' ) ),
				array( 'rank' => 6, 'asin' => 'B0C2F5KD26', 'title' => 'Sony WF-C700N Truly Wireless Noise Canceling Earbuds', 'brand' => 'Sony', 'image' => 'https://m.media-amazon.com/images/I/51c1HrxYQ3L._AC_SL1500_.jpg', 'price' => 89.99, 'rating' => 4.2, 'review_count' => 6700, 'badge' => 'balanced', 'why' => 'A balanced mid-range pair for shoppers who want Sony tuning and noise cancellation without paying flagship prices.', 'pros' => array( 'Sony sound profile', 'ANC included', 'Lightweight fit' ), 'cons' => array( 'No wireless charging', 'Call quality is average' ), 'features' => array( 'ANC', 'Ambient sound', 'IPX4 resistance' ) ),
				array( 'rank' => 7, 'asin' => 'B0C8PR4W22', 'title' => 'Beats Studio Buds + True Wireless Noise Cancelling Earbuds', 'brand' => 'Beats', 'image' => 'https://m.media-amazon.com/images/I/61waScs8JrL._AC_SL1500_.jpg', 'price' => 129.99, 'rating' => 4.5, 'review_count' => 14600, 'badge' => 'calls_travel', 'why' => 'A stylish cross-platform option for shoppers who want ANC, compact travel use, and simple pairing on both iPhone and Android.', 'pros' => array( 'Works well across platforms', 'Good ANC', 'Compact case' ), 'cons' => array( 'No wireless charging', 'Fit is personal' ), 'features' => array( 'ANC', 'Transparency mode', 'Cross-platform pairing' ) ),
			),
		),
	);
}

/**
 * Match a shopper query to a bundled catalog group.
 */
function pr_default_catalog_key_for_keyword( string $keyword ): string {
	$norm = function_exists( 'yadfood_normalize_query' ) ? yadfood_normalize_query( $keyword ) : strtolower( $keyword );
	$norm = strtolower( $norm );

	$rules = array(
		'coffee-beans'     => array( 'coffee', 'bean', 'espresso', 'lavazza' ),
		'robot-vacuums'    => array( 'vacuum', 'roomba', 'robot', 'cleaner', 'pet hair' ),
		'wireless-earbuds' => array( 'earbud', 'earbuds', 'headphone', 'airpods', 'audio', 'bluetooth' ),
	);
	foreach ( $rules as $key => $needles ) {
		foreach ( $needles as $needle ) {
			if ( false !== strpos( $norm, $needle ) ) {
				return $key;
			}
		}
	}
	return '';
}

/**
 * Tagged Amazon search URL for safe zero-config fallback results.
 */
function pr_default_amazon_search_url( string $keyword, string $subtag = '' ): string {
	$args = array(
		'k'   => sanitize_text_field( $keyword ),
		'tag' => function_exists( 'pr_affiliate_tag' ) ? pr_affiliate_tag() : 'YOUR-TAG-20',
	);
	if ( '' !== $subtag ) {
		$args['ascsubtag'] = sanitize_title( $subtag );
	}
	return add_query_arg( $args, 'https://www.amazon.com/s' );
}

/**
 * Products for a query, copied so callers can safely mutate rows.
 */
function pr_default_products_for_keyword( string $keyword, int $count = 10 ): array {
	$catalog = pr_default_product_catalog();
	$key     = pr_default_catalog_key_for_keyword( $keyword );
	$rows    = $key && isset( $catalog[ $key ]['products'] ) ? (array) $catalog[ $key ]['products'] : array();

	if ( empty( $rows ) ) {
		$label = trim( sanitize_text_field( $keyword ) );
		$label = '' !== $label ? $label : 'popular products';
		return array(
			array(
				'rank'         => 1,
				'asin'         => '',
				'title'        => sprintf( 'View current Amazon results for %s', ucwords( $label ) ),
				'brand'        => 'Amazon',
				'image'        => '',
				'price'        => '',
				'currency'     => 'USD',
				'rating'       => '',
				'review_count' => '',
				'features'     => array( 'Live Amazon marketplace results', 'Affiliate tag applied', 'Use this until you add/edit exact product picks' ),
				'pros'         => array(),
				'cons'         => array(),
				'why'          => 'No bundled template list matched this exact search, so this safe fallback sends shoppers to current Amazon results instead of showing unrelated products.',
				'badge'        => 'live_search',
				'url'          => pr_default_amazon_search_url( $label, 'fallback-search' ),
			),
		);
	}

	foreach ( $rows as $i => &$row ) {
		$row = wp_parse_args( (array) $row, array(
			'rank'         => $i + 1,
			'asin'         => '',
			'title'        => '',
			'brand'        => '',
			'image'        => '',
			'price'        => '',
			'currency'     => 'USD',
			'rating'       => '',
			'review_count' => '',
			'features'     => array(),
			'pros'         => array(),
			'cons'         => array(),
			'why'          => '',
			'badge'        => '',
		) );
		$row['rank'] = $i + 1;
	}

	return array_slice( $rows, 0, max( 1, min( 10, $count ) ) );
}

/**
 * Create a category term if it does not exist.
 */
function pr_default_ensure_category( string $name ): int {
	$slug = sanitize_title( $name );
	$term = get_term_by( 'slug', $slug, 'review_category' );
	if ( $term && ! is_wp_error( $term ) ) {
		return (int) $term->term_id;
	}
	$res = wp_insert_term( $name, 'review_category', array( 'slug' => $slug ) );
	return is_wp_error( $res ) ? 0 : (int) $res['term_id'];
}

/**
 * Seed editable review posts so the homepage, archive, and search are useful immediately.
 */
function pr_seed_default_reviews( bool $force = false ): void {
	if ( ! $force && get_option( 'pr_default_reviews_seeded' ) ) {
		return;
	}

	if ( ! post_type_exists( 'review' ) && function_exists( 'yadfood_register_review_cpt' ) ) {
		yadfood_register_review_cpt();
	}
	if ( ! taxonomy_exists( 'review_category' ) && function_exists( 'pr_register_taxonomies' ) ) {
		pr_register_taxonomies();
	}

	if ( ! post_type_exists( 'review' ) || ! taxonomy_exists( 'review_category' ) ) {
		return;
	}

	foreach ( pr_default_product_catalog() as $key => $group ) {
		$existing = get_page_by_path( sanitize_title( $group['title'] ), OBJECT, 'review' );
		if ( $existing instanceof WP_Post ) {
			continue;
		}

		$post_id = wp_insert_post( array(
			'post_type'    => 'review',
			'post_status'  => 'publish',
			'post_title'   => sanitize_text_field( $group['title'] ),
			'post_name'    => sanitize_title( $group['title'] ),
			'post_excerpt' => sanitize_text_field( $group['tldr'] ),
			'post_content' => wp_kses_post( $group['intro'] ),
		), true );

		if ( is_wp_error( $post_id ) || ! $post_id ) {
			continue;
		}

		update_post_meta( $post_id, '_yadfood_keyword', sanitize_text_field( $group['keyword'] ) );
		update_post_meta( $post_id, '_yadfood_tldr', sanitize_text_field( $group['tldr'] ) );
		update_post_meta( $post_id, '_yadfood_intro', wp_kses_post( $group['intro'] ) );
		update_post_meta( $post_id, '_yadfood_products', (array) $group['products'] );
		update_post_meta( $post_id, '_yadfood_faqs', (array) $group['faqs'] );
		update_post_meta( $post_id, '_yadfood_buyers', (array) $group['buyers'] );
		update_post_meta( $post_id, '_pr_is_default_template', '1' );

		$term_id = pr_default_ensure_category( (string) $group['category'] );
		if ( $term_id ) {
			wp_set_post_terms( $post_id, array( $term_id ), 'review_category', false );
		}
	}

	update_option( 'pr_default_reviews_seeded', '1', false );
}
add_action( 'after_switch_theme', 'pr_seed_default_reviews', 30 );
add_action( 'init', function () {
	$count     = wp_count_posts( 'review' );
	$published = $count && isset( $count->publish ) ? (int) $count->publish : 0;
	if ( 0 === $published ) {
		pr_seed_default_reviews( true );
	}
}, 30 );

/**
 * Backfill default reviews if a previous ZIP was installed before this seeder existed.
 */
function pr_maybe_seed_default_reviews_on_admin(): void {
	if ( is_admin() && ! wp_doing_ajax() ) {
		$count = wp_count_posts( 'review' );
		$published = $count && isset( $count->publish ) ? (int) $count->publish : 0;
		if ( 0 === $published ) {
			pr_seed_default_reviews( true );
		}
	}
}
add_action( 'admin_init', 'pr_maybe_seed_default_reviews_on_admin' );
