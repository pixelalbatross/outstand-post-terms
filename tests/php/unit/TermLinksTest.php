<?php
/**
 * Tests for the Post Terms link setting.
 *
 * @package Outstand\WP\PostTerms\Tests\Unit
 */

namespace Outstand\WP\PostTerms\Tests\Unit;

use Outstand\WP\PostTerms\TermLinks;
use WP_Block_Type_Registry;
use WP_Block;

/**
 * TermLinks test case.
 */
class TermLinksTest extends \WP_UnitTestCase {

	/**
	 * Taxonomy registered for the tests.
	 *
	 * @var string
	 */
	const TAXONOMY = 'ospt_topic';

	/**
	 * Post the rendered blocks read terms from.
	 *
	 * @var int
	 */
	private int $post_id;

	/**
	 * {@inheritDoc}
	 */
	public function set_up(): void {
		parent::set_up();

		register_taxonomy(
			self::TAXONOMY,
			'post',
			[
				'public'       => true,
				'show_in_rest' => true,
			]
		);

		$this->post_id = self::factory()->post->create();
	}

	/**
	 * {@inheritDoc}
	 */
	public function tear_down(): void {
		remove_all_filters( 'term_links-' . self::TAXONOMY );
		unregister_taxonomy( self::TAXONOMY );
		parent::tear_down();
	}

	/**
	 * The Post Terms block registers isLink, defaulting to true.
	 *
	 * @return void
	 */
	public function test_block_registers_is_link_attribute(): void {

		$block_type = WP_Block_Type_Registry::get_instance()->get_registered( 'core/post-terms' );

		$this->assertSame(
			[
				'type'    => 'boolean',
				'default' => true,
			],
			$block_type->attributes['isLink']
		);
	}

	/**
	 * An isLink attribute that already exists is kept.
	 *
	 * @return void
	 */
	public function test_existing_is_link_attribute_is_kept(): void {

		$existing = [
			'type'    => 'boolean',
			'default' => false,
		];

		$args = ( new TermLinks() )->filter_block_type_args(
			[ 'attributes' => [ 'isLink' => $existing ] ],
			'core/post-terms'
		);

		$this->assertSame( $existing, $args['attributes']['isLink'] );
	}

	/**
	 * Other blocks are left untouched.
	 *
	 * @return void
	 */
	public function test_other_blocks_are_untouched(): void {

		$args = [ 'attributes' => [] ];

		$filtered = ( new TermLinks() )->filter_block_type_args( $args, 'core/post-title' );

		$this->assertSame( $args, $filtered );
	}

	/**
	 * Terms stay linked when isLink is not set.
	 *
	 * @return void
	 */
	public function test_terms_stay_linked_by_default(): void {

		wp_set_object_terms( $this->post_id, [ 'Alpha', 'Beta' ], self::TAXONOMY );

		$html = $this->render_post_terms( [ 'term' => self::TAXONOMY ] );

		$this->assertStringContainsString( '<a href=', $html );
		$this->assertStringContainsString( 'rel="tag">Alpha</a>', $html );
	}

	/**
	 * Terms render as spans when isLink is false.
	 *
	 * @return void
	 */
	public function test_terms_render_as_spans_when_links_are_off(): void {

		wp_set_object_terms( $this->post_id, [ 'Alpha', 'Beta' ], self::TAXONOMY );

		$html = $this->render_post_terms(
			[
				'term'   => self::TAXONOMY,
				'isLink' => false,
			]
		);

		$this->assertStringNotContainsString( '<a ', $html );
		$this->assertStringContainsString(
			'<span class="wp-block-post-terms__term">Alpha</span><span class="wp-block-post-terms__separator">, </span><span class="wp-block-post-terms__term">Beta</span>',
			$html
		);
	}

	/**
	 * Term names are escaped exactly once.
	 *
	 * @return void
	 */
	public function test_term_names_are_escaped_once(): void {

		wp_set_object_terms( $this->post_id, [ 'Salt & Pepper' ], self::TAXONOMY );

		$html = $this->render_post_terms(
			[
				'term'   => self::TAXONOMY,
				'isLink' => false,
			]
		);

		$this->assertStringContainsString( '<span class="wp-block-post-terms__term">Salt &amp; Pepper</span>', $html );
		$this->assertStringNotContainsString( '&amp;amp;', $html );
	}

	/**
	 * Term lists outside the unlinked block keep their links.
	 *
	 * @return void
	 */
	public function test_other_term_lists_keep_their_links(): void {

		wp_set_object_terms( $this->post_id, [ 'Alpha' ], self::TAXONOMY );

		$this->render_post_terms(
			[
				'term'   => self::TAXONOMY,
				'isLink' => false,
			]
		);

		$list = get_the_term_list( $this->post_id, self::TAXONOMY );

		$this->assertStringContainsString( '<a href=', $list );
	}

	/**
	 * A linked block rendered after an unlinked one keeps its links.
	 *
	 * @return void
	 */
	public function test_linked_block_after_unlinked_block_keeps_links(): void {

		wp_set_object_terms( $this->post_id, [ 'Alpha' ], self::TAXONOMY );

		$this->render_post_terms(
			[
				'term'   => self::TAXONOMY,
				'isLink' => false,
			]
		);

		$html = $this->render_post_terms( [ 'term' => self::TAXONOMY ] );

		$this->assertStringContainsString( 'rel="tag">Alpha</a>', $html );
	}

	/**
	 * Links changed by other term_links filters are still unlinked.
	 *
	 * @return void
	 */
	public function test_unlinks_links_changed_by_other_filters(): void {

		wp_set_object_terms( $this->post_id, [ 'Alpha' ], self::TAXONOMY );

		add_filter(
			'term_links-' . self::TAXONOMY,
			fn( $links ) => array_map( fn( $link ) => str_replace( '<a ', '<a class="ospt-extra" ', $link ), $links )
		);

		$html = $this->render_post_terms(
			[
				'term'   => self::TAXONOMY,
				'isLink' => false,
			]
		);

		$this->assertStringNotContainsString( '<a ', $html );
		$this->assertStringContainsString( '<span class="wp-block-post-terms__term">Alpha</span>', $html );
	}

	/**
	 * The temporary filter is removed even when rendering throws.
	 *
	 * @return void
	 */
	public function test_filter_is_removed_when_render_throws(): void {

		$module     = new TermLinks();
		$attributes = [
			'term'   => self::TAXONOMY,
			'isLink' => false,
		];

		try {
			$module->render(
				function () {
					throw new \RuntimeException( 'Render failed.' );
				},
				$attributes,
				'',
				$this->make_block( $attributes )
			);
		} catch ( \RuntimeException $e ) {
			$this->assertSame( 'Render failed.', $e->getMessage() );
		}

		$this->assertFalse( has_filter( 'term_links-' . self::TAXONOMY, [ $module, 'unlink_terms' ] ) );
	}

	/**
	 * A post with no terms in the taxonomy renders nothing.
	 *
	 * @return void
	 */
	public function test_post_without_terms_renders_nothing(): void {

		$html = $this->render_post_terms(
			[
				'term'   => self::TAXONOMY,
				'isLink' => false,
			]
		);

		$this->assertSame( '', $html );
	}

	/**
	 * A block with no taxonomy set renders nothing.
	 *
	 * @return void
	 */
	public function test_block_without_taxonomy_renders_nothing(): void {

		$html = $this->render_post_terms( [ 'isLink' => false ] );

		$this->assertSame( '', $html );
	}

	/**
	 * Render a Post Terms block for the test post.
	 *
	 * @param  array $attributes Block attributes.
	 * @return string
	 */
	private function render_post_terms( array $attributes ): string {
		return $this->make_block( $attributes )->render();
	}

	/**
	 * Build a Post Terms block instance with the test post as context.
	 *
	 * @param  array $attributes Block attributes.
	 * @return WP_Block
	 */
	private function make_block( array $attributes ): WP_Block {
		return new WP_Block(
			[
				'blockName'    => 'core/post-terms',
				'attrs'        => $attributes,
				'innerBlocks'  => [],
				'innerHTML'    => '',
				'innerContent' => [],
			],
			[
				'postId'   => $this->post_id,
				'postType' => 'post',
			]
		);
	}
}
