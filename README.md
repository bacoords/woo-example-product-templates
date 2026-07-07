# Woo Example Product Templates

Example WooCommerce plugin that registers category-specific block templates for single product pages.

This is meant as tutorial code, not a complete template-management product. It keeps the configuration in PHP so developers can see the moving parts clearly.

## What it does

- Registers one single product block template per configured product category slug.
- Uses the current site's `single-product` block template as the default content for each category template.
- Adds matching category templates into WordPress's single product template hierarchy.
- Lets Site Editor customizations win over the registered defaults.

For example, the configured `music` category slug registers this template:

```text
single-product-category-music
```

If a product belongs to the `music` category, WordPress checks that category template after product-specific templates and before the normal `single-product` fallback.

## Configure categories

Edit `Product_Category_Templates::CATEGORY_SLUGS` in `woo-example-product-templates.php`:

```php
private const CATEGORY_SLUGS = array(
	'clothing',
	'decor',
	'music',
);
```

Use top-level product category slugs from your site. Products assigned to child categories can route through a configured ancestor category. For example, a product in `tshirts` can use the `clothing` template if `clothing` is listed.

## Template behavior

The plugin does not ship HTML template files. Each registered category template starts with the site's current `single-product` block template content.

Once a category template is edited in the Site Editor, WordPress stores it as a `wp_template` post for the active theme. That saved template takes precedence over the plugin's registered default.

## Requirements

- WordPress with block template support.
- WooCommerce active.
- A block theme or theme support for block templates.

## Notes

Template slugs are derived from category slugs, matching normal WordPress template hierarchy conventions. If a category slug changes, the derived template slug changes too.
