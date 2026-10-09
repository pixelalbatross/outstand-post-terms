<?php

namespace Outstand\WP\PostTerms;

defined( 'ABSPATH' ) || exit;

use WP_Block;

/**
 * Adds an `isLink` setting to the core Post Terms block. When it is off, the
 * block lists the post's terms as plain text.
 */
class TermLinks extends BaseModule {

	/**
	 * Block this module extends.
	 *
	 * @var string
	 */
	const BLOCK_NAME = 'core/post-terms';

	/**
	 * Class of the element wrapping each unlinked term.
	 *
	 * @var string
	 */
	const TERM_CLASS = 'wp-block-post-terms__term';

	/**
	 * {@inheritDoc}
	 */
	public function register(): void {
		add_filter( 'register_block_type_args', [ $this, 'filter_block_type_args' ], 10, 2 );
	}

	/**
	 * Add the `isLink` attribute to the Post Terms block and wrap its render
	 * callback.
	 *
	 * @param  array  $args       Block type arguments.
	 * @param  string $block_name Block type name.
	 * @return array
	 */
	public function filter_block_type_args( array $args, string $block_name ): array {

		if ( self::BLOCK_NAME !== $block_name ) {
			return $args;
		}

		// A core `isLink` attribute takes precedence when one exists.
		if ( ! isset( $args['attributes']['isLink'] ) ) {
			$args['attributes']['isLink'] = [
				'type'    => 'boolean',
				'default' => true,
			];
		}

		if ( ! isset( $args['render_callback'] ) || ! is_callable( $args['render_callback'] ) ) {
			return $args;
		}

		$render_callback = $args['render_callback'];

		$args['render_callback'] = function ( $attributes, $content, $block ) use ( $render_callback ) {
			return $this->render( $render_callback, (array) $attributes, (string) $content, $block );
		};

		return $args;
	}

	/**
	 * Render the block, replacing term links with plain text when `isLink` is
	 * off.
	 *
	 * The `term_links-{$taxonomy}` filter is attached only for this render, so
	 * other term lists on the page keep their links. It runs last so links
	 * already changed by other plugins are unlinked too.
	 *
	 * @param  callable $render_callback Original render callback.
	 * @param  array    $attributes      Block attributes.
	 * @param  string   $content         Block content.
	 * @param  WP_Block $block           Block instance.
	 * @return string
	 */
	public function render( callable $render_callback, array $attributes, string $content, WP_Block $block ): string {

		$is_link  = $attributes['isLink'] ?? true;
		$taxonomy = $attributes['term'] ?? '';

		if ( false !== $is_link || '' === $taxonomy ) {
			return (string) call_user_func( $render_callback, $attributes, $content, $block );
		}

		$hook = "term_links-{$taxonomy}";

		add_filter( $hook, [ $this, 'unlink_terms' ], PHP_INT_MAX );

		try {
			return (string) call_user_func( $render_callback, $attributes, $content, $block );
		} finally {
			remove_filter( $hook, [ $this, 'unlink_terms' ], PHP_INT_MAX );
		}
	}

	/**
	 * Replace each term link with a span holding the term name.
	 *
	 * @param  array $links Term link markup.
	 * @return array
	 */
	public function unlink_terms( array $links ): array {
		return array_map(
			fn( $link ) => sprintf(
				'<span class="%1$s">%2$s</span>',
				esc_attr( self::TERM_CLASS ),
				esc_html( wp_strip_all_tags( $link ) )
			),
			$links
		);
	}
}
