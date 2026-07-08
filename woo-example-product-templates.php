<?php
/**
 * Plugin Name: Woo Example Product Templates
 * Description: Registers category-routed WooCommerce single product block templates using the site's product template as the default.
 * Version: 0.1.0
 * Author: Woo Developer Advocacy
 * Text Domain: woo-example-product-templates
 * Requires Plugins: woocommerce
 * Requires PHP: 7.4
 *
 * @package WooExampleProductTemplates
 */

declare( strict_types=1 );

namespace WooExampleProductTemplates;

defined( 'ABSPATH' ) || exit;

const TEMPLATE_NAMESPACE   = 'woo-example-product-templates';
const TEMPLATE_SLUG_PREFIX = 'single-product-category-';

/**
 * Product category slugs used by this example.
 *
 * Change this list to match exact top-level product category slugs on your site.
 */
const CATEGORY_SLUGS = array(
	'clothing',
	'decor',
	'music',
);

add_action( 'init', __NAMESPACE__ . '\register_product_templates' );
add_filter( 'single_template_hierarchy', __NAMESPACE__ . '\add_category_templates_to_single_product_hierarchy' );

/**
 * Register the category-routed product block templates.
 */
function register_product_templates(): void {
	if ( ! function_exists( 'register_block_template' ) ) {
		return;
	}

	$registry         = \WP_Block_Templates_Registry::get_instance();
	$template_content = get_single_product_template_content();

	foreach ( CATEGORY_SLUGS as $category_slug ) {
		$category_label = ucwords( str_replace( '-', ' ', $category_slug ) );
		$template_slug = get_template_slug( $category_slug );
		$template_name = TEMPLATE_NAMESPACE . '//' . $template_slug;

		if ( $registry->is_registered( $template_name ) ) {
			continue;
		}

		register_block_template(
			$template_name,
			array(
				'title'       => sprintf(
					/* translators: %s: Product category name. */
					__( 'Single Product: %s Category', 'woo-example-product-templates' ),
					$category_label
				),
				'description' => sprintf(
					/* translators: %s: Product category name. */
					__( 'Displays single products in the %s category.', 'woo-example-product-templates' ),
					$category_label
				),
				'content'     => $template_content,
				'post_types'  => array( 'product' ),
			)
		);
	}
}

/**
 * Get the default content for category-routed product templates.
 *
 * @return string
 */
function get_single_product_template_content(): string {
	$site_template = get_block_template( get_stylesheet() . '//single-product', 'wp_template' );

	if ( $site_template instanceof \WP_Block_Template && '' !== trim( (string) $site_template->content ) ) {
		return (string) $site_template->content;
	}

	$woocommerce_template = get_block_template( 'woocommerce/woocommerce//single-product', 'wp_template' );

	if ( $woocommerce_template instanceof \WP_Block_Template && '' !== trim( (string) $woocommerce_template->content ) ) {
		return (string) $woocommerce_template->content;
	}

	return '';
}

/**
 * Add matching category templates to the single product block template hierarchy.
 *
 * @param string[] $templates Ordered template hierarchy candidates.
 * @return string[]
 */
function add_category_templates_to_single_product_hierarchy( array $templates ): array {
	if ( ! is_singular( 'product' ) ) {
		return $templates;
	}

	$post = get_queried_object();

	if ( ! $post instanceof \WP_Post || 'product' !== $post->post_type ) {
		return $templates;
	}

	$template_slugs = get_product_category_template_slugs( (int) $post->ID );

	if ( array() === $template_slugs ) {
		return $templates;
	}

	$template_files = array_map(
		static function ( string $template_slug ): string {
			return $template_slug . '.php';
		},
		$template_slugs
	);

	$template_files = array_values(
		array_filter(
			$template_files,
			static function ( string $template_file ) use ( $templates ): bool {
				return ! in_array( $template_file, $templates, true );
			}
		)
	);

	if ( array() === $template_files ) {
		return $templates;
	}

	$insert_at = array_search( 'single-product.php', $templates, true );

	if ( false === $insert_at ) {
		$insert_at = array_search( 'single.php', $templates, true );
	}

	if ( false === $insert_at ) {
		return array_merge( $templates, $template_files );
	}

	array_splice( $templates, (int) $insert_at, 0, $template_files );

	return $templates;
}

/**
 * Return matching template slugs for a product's categories.
 *
 * Directly assigned categories are checked first. Ancestors are checked only
 * when no direct category matches.
 *
 * @param int $product_id Product ID.
 * @return string[] Template slugs.
 */
function get_product_category_template_slugs( int $product_id ): array {
	$terms = get_the_terms( $product_id, 'product_cat' );

	if ( empty( $terms ) || is_wp_error( $terms ) ) {
		return array();
	}

	$direct_slugs   = wp_list_pluck( $terms, 'slug' );
	$matched_slugs  = array_values( array_intersect( CATEGORY_SLUGS, $direct_slugs ) );

	if ( array() === $matched_slugs ) {
		$ancestor_slugs = get_product_category_ancestor_slugs( $terms );
		$matched_slugs  = array_values( array_intersect( CATEGORY_SLUGS, $ancestor_slugs ) );
	}

	return array_map( __NAMESPACE__ . '\get_template_slug', $matched_slugs );
}

/**
 * Get product category ancestor slugs for a list of assigned terms.
 *
 * @param \WP_Term[] $terms Product category terms.
 * @return string[]
 */
function get_product_category_ancestor_slugs( array $terms ): array {
	$ancestor_slugs = array();

	foreach ( $terms as $term ) {
		if ( ! $term instanceof \WP_Term ) {
			continue;
		}

		$ancestor_ids = get_ancestors( $term->term_id, 'product_cat', 'taxonomy' );

		foreach ( $ancestor_ids as $ancestor_id ) {
			$ancestor = get_term( $ancestor_id, 'product_cat' );

			if ( $ancestor instanceof \WP_Term ) {
				$ancestor_slugs[] = $ancestor->slug;
			}
		}
	}

	return array_values( array_unique( $ancestor_slugs ) );
}

/**
 * Get a template slug for a category slug.
 *
 * @param string $category_slug Product category slug.
 * @return string
 */
function get_template_slug( string $category_slug ): string {
	return TEMPLATE_SLUG_PREFIX . $category_slug;
}
