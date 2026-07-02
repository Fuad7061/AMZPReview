<?php get_header(); ?>
<div class="yf-container yf-404">
	<h1>404 — Page not found</h1>
	<p>That page doesn't exist. Try the homepage or search.</p>
	<form class="yf-hero__search" role="search" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>">
		<input type="search" name="s" placeholder="Search reviews…">
		<button type="submit">Search</button>
	</form>
</div>
<?php get_footer(); ?>
