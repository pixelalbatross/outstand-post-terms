<?php

namespace Outstand\WP\PostTerms;

defined( 'ABSPATH' ) || exit;

class Assets extends BaseModule {
	use GetAssetInfo;

	/**
	 * Script handle.
	 *
	 * @var string
	 */
	const HANDLE = 'outstand-post-terms-editor';

	/**
	 * {@inheritDoc}
	 */
	public function register(): void {
		$this->setup_asset_vars(
			dist_path: OUTSTAND_POST_TERMS_DIST_PATH,
			fallback_version: OUTSTAND_POST_TERMS_VERSION
		);

		add_action( 'enqueue_block_editor_assets', [ $this, 'enqueue_block_editor_scripts' ] );
		add_action( 'enqueue_block_assets', [ $this, 'enqueue_block_assets' ] );
	}

	/**
	 * Enqueue the editor bundle.
	 *
	 * @return void
	 */
	public function enqueue_block_editor_scripts(): void {

		wp_enqueue_script(
			self::HANDLE,
			OUTSTAND_POST_TERMS_DIST_URL . 'js/editor.js',
			$this->get_asset_info( 'editor', 'dependencies' ),
			$this->get_asset_info( 'editor', 'version' ),
			true
		);

		wp_set_script_translations(
			self::HANDLE,
			'outstand-post-terms',
			OUTSTAND_POST_TERMS_PATH . 'languages'
		);
	}

	/**
	 * Enqueue the editor stylesheet inside the editor canvas.
	 *
	 * `enqueue_block_assets` also runs on the front end, where the class this
	 * stylesheet targets never appears.
	 *
	 * @return void
	 */
	public function enqueue_block_assets(): void {

		if ( ! is_admin() ) {
			return;
		}

		wp_enqueue_style(
			self::HANDLE,
			OUTSTAND_POST_TERMS_DIST_URL . 'js/editor.css',
			[],
			$this->get_asset_info( 'editor', 'version' )
		);
	}
}
