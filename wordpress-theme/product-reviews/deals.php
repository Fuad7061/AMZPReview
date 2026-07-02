<?php
/**
 * Template: /deals virtual archive.
 * Triggered by the rewrite rule in inc/deals.php (pr_deals=1).
 */
get_header();
?>
<main id="primary" class="site-main pr-deals-page">
	<header class="pr-deals-hero">
		<h1>Today's best deals</h1>
		<p class="pr-deals-sub">Live picks ranked by savings. Updated continuously from Amazon pricing.</p>
	</header>
	<?php echo pr_render_deals_list( 48 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
</main>
<?php
get_footer();
