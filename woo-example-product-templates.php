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

const TEMPLATE_NAMESPACE  = 'woo-example-product-templates';
const MUSIC_TEMPLATE_SLUG = 'single-product-category-music';
const MUSIC_CATEGORY_SLUG = 'music';

add_action( 'init', __NAMESPACE__ . '\register_product_templates' );
add_filter( 'single_template_hierarchy', __NAMESPACE__ . '\add_category_template_to_single_product_hierarchy' );

/**
 * Register the category-routed product block templates.
 */
function register_product_templates(): void {
	if ( ! function_exists( 'register_block_template' ) || ! class_exists( '\WP_Block_Templates_Registry' ) ) {
		return;
	}

	$template_name = TEMPLATE_NAMESPACE . '//' . MUSIC_TEMPLATE_SLUG;
	$registry      = \WP_Block_Templates_Registry::get_instance();

	if ( $registry->is_registered( $template_name ) ) {
		return;
	}

	register_block_template(
		$template_name,
		array(
			'title'       => __( 'Single Product: Music Category', 'woo-example-product-templates' ),
			'description' => __( 'Displays single products in the Music category.', 'woo-example-product-templates' ),
			'content'     => get_single_product_template_content(),
			'post_types'  => array( 'product' ),
		)
	);
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
 * Add the matching category template to the single product block template hierarchy.
 *
 * @param string[] $templates Ordered template hierarchy candidates.
 * @return string[]
 */
function add_category_template_to_single_product_hierarchy( array $templates ): array {
	if ( ! is_singular( 'product' ) ) {
		return $templates;
	}

	$post = get_queried_object();

	if ( ! $post instanceof \WP_Post || 'product' !== $post->post_type ) {
		return $templates;
	}

	$template_slug = get_product_category_template_slug( (int) $post->ID );

	if ( '' === $template_slug ) {
		return $templates;
	}

	$template_file = $template_slug . '.php';

	if ( in_array( $template_file, $templates, true ) ) {
		return $templates;
	}

	$insert_at = array_search( 'single-product.php', $templates, true );

	if ( false === $insert_at ) {
		$insert_at = array_search( 'single.php', $templates, true );
	}

	if ( false === $insert_at ) {
		$templates[] = $template_file;
		return $templates;
	}

	array_splice( $templates, (int) $insert_at, 0, array( $template_file ) );

	return $templates;
}

/**
 * Return the first matching template slug for a product's categories.
 *
 * Directly assigned categories are checked first. Ancestors are checked only
 * when no direct category matches.
 *
 * @param int $product_id Product ID.
 * @return string Template slug, or an empty string when no mapped category matches.
 */
function get_product_category_template_slug( int $product_id ): string {
	$terms = get_the_terms( $product_id, 'product_cat' );

	if ( empty( $terms ) || is_wp_error( $terms ) ) {
		return '';
	}

	$template_map = get_category_template_map();
	$term_slugs   = wp_list_pluck( $terms, 'slug' );

	foreach ( $template_map as $category_slug => $template_slug ) {
		if ( in_array( $category_slug, $term_slugs, true ) ) {
			return $template_slug;
		}
	}

	$ancestor_slugs = get_product_category_ancestor_slugs( $terms );

	foreach ( $template_map as $category_slug => $template_slug ) {
		if ( in_array( $category_slug, $ancestor_slugs, true ) ) {
			return $template_slug;
		}
	}

	return '';
}

/**
 * Get the category-to-template map.
 *
 * @return array<string,string>
 */
function get_category_template_map(): array {
	return array(
		MUSIC_CATEGORY_SLUG => MUSIC_TEMPLATE_SLUG,
	);
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
