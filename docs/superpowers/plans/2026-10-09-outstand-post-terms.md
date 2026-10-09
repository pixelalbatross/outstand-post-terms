# Outstand Post Terms Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a "Make terms links" toggle to the core Post Terms block so a post can list its terms as plain text on sites without taxonomy archive pages.

**Architecture:** A new Outstand plugin (`outstand-post-terms`) extends `core/post-terms` with an `isLink` boolean attribute (default `true`). On the server, a `register_block_type_args` filter adds the attribute and wraps the core render callback. When `isLink` is `false`, the wrapper attaches a `term_links-{$taxonomy}` filter for that one render, which turns each `<a>` into `<span class="wp-block-post-terms__term">`. In the editor, JS filters add the same attribute, an inspector toggle, and a wrapper class whose CSS renders the editor's term links as plain text.

**Tech Stack:** PHP 8.2, WordPress 7.1 block APIs, `@wordpress/scripts` (webpack, Jest), `@wordpress/env` + PHPUnit 9.6 integration tests, Composer, PixelAlbatross PHPCS, plugin-update-checker v5.

**Spec:** No separate spec file. The design was agreed in chat and is recorded in the "Design" section below. Executors also follow the `outstand-plugin` skill (`~/Projects/outstand-skills/skills/outstand-plugin/SKILL.md`), the house standard this plugin is scaffolded from.

## Design

- **Scope:** only `core/post-terms`, which covers its Categories and Tags variations and any custom taxonomy. No global setting, no automatic detection of taxonomies without archives, no public hooks.
- **Attribute:** `isLink`, `{ type: 'boolean', default: true }`, registered in PHP (`register_block_type_args`) and JS (`blocks.registerBlockType`). Saved blocks without the attribute keep their links. When core ever registers its own `isLink` on this block, the plugin leaves core's definition in place.
- **Editor control:** a `ToggleControl` labeled "Make terms links" in a `ToolsPanelItem` (shown by default) inside a "Settings" `ToolsPanel` in `InspectorControls`, added through `editor.BlockEdit`. This is the panel core's Post Title block uses for its own `isLink` toggle. Resetting the panel or deselecting the item sets `isLink` back to `true`. `ToolsPanel` and `ToolsPanelItem` ship only as `__experimental*` exports, so each import specifier carries its own `@wordpress/no-unsafe-wp-apis` disable.
- **Front-end markup when `isLink` is `false`:**

  ```html
  <div class="taxonomy-category wp-block-post-terms">
    <span class="wp-block-post-terms__term">News</span><span class="wp-block-post-terms__separator">, </span><span class="wp-block-post-terms__term">Events</span>
  </div>
  ```

  The span sits where core puts the `<a>`, so each term keeps a styling hook. The name is `esc_html( wp_strip_all_tags( $link ) )`.
- **Render scoping:** the wrapper adds the `term_links-{$taxonomy}` filter at `PHP_INT_MAX`, calls the original callback, and removes the filter in a `finally` block. Other `get_the_term_list()` calls on the page and linked Post Terms blocks are untouched. Running last means links already changed by other plugins are still unlinked.
- **Editor preview:** core's editor component always renders `<a href … onClick={ preventDefault }>`. An `editor.BlockListBlock` filter adds the class `outstand-post-terms-unlinked` to unlinked blocks. An editor stylesheet, loaded into the editor iframe through `enqueue_block_assets` with an `is_admin()` check, sets those links to `color: inherit; text-decoration: none; pointer-events: none`.

## Global Constraints

- Plugin path: `/Users/s3rgiosan/www/outstand/app/public/wp-content/plugins/outstand-post-terms` (referred to below as `$PLUGIN`).
- Title `Outstand Post Terms`; slug and text domain `outstand-post-terms`; namespace `Outstand\WP\PostTerms`; constant prefix `OUTSTAND_POST_TERMS`; Composer name `outstand/post-terms`; JS entry `editor`; script and style handle `outstand-post-terms-editor`.
- Description: `Adds a setting to the Post Terms block that lists terms without links.`
- Version `1.0.0`, kept identical in the `Version:` header, `OUTSTAND_POST_TERMS_VERSION`, and `package.json`.
- Requires at least WordPress `7.1`, PHP `8.2`. Form controls take no `__nextHasNoMarginBottom` / `__next40pxDefaultSize` props.
- Attribute name `isLink`, default `true`. Unlinked term class `wp-block-post-terms__term`. Editor wrapper class `outstand-post-terms-unlinked`.
- Test fixture storage token: `ospt` (taxonomy `ospt_topic`). Test data is fabricated (`Alpha`, `Beta`, `Salt & Pepper`).
- wp-env ports `8995` (dev) and `8996` (tests), which keeps clear of `outstand-term-selector` (8997/8998).
- House rules: PHP members ordered `public` → `protected` → `private` (constants and properties first); `global` declarations first in a method; compared calls/casts hoisted into locals; `switch` for 3+ literal branches on one variable; comments state what the code does, with no provenance or "instead of" phrasing; every shipped PHP file starts with `defined( 'ABSPATH' ) || exit;` (or the `WPINC` check in `plugin.php`).
- Commits: conventional, lowercase, no AI attribution or session links. Tags are plain semver (`1.0.0`).
- Known core limitation, documented in the README and left unchanged: `core/post-terms` renders nothing for taxonomies that are not publicly queryable (`is_taxonomy_viewable()` on the server, `visibility.publicly_queryable` in the editor). Sites keep the taxonomy publicly queryable and use this plugin to drop the links.

## Review Focus

1. **Another plugin filters `term_links-{$taxonomy}`** (an SEO plugin adds attributes or classes to the links): the output is still plain spans with clean names. Pinned in Task 3 (`test_unlinks_links_changed_by_other_filters`).
2. **A linked and an unlinked Post Terms block on the same page, or a theme calling `get_the_term_list()` after the block:** only the unlinked block loses its links. Pinned in Task 3 (`test_other_term_lists_keep_their_links`, `test_linked_block_after_unlinked_block_keeps_links`).
3. **The original render callback throws:** the temporary filter is still removed, so later term lists keep their links. Pinned in Task 3 (`test_filter_is_removed_when_render_throws`).
4. **Term names with special characters (`&`, quotes):** the name is escaped exactly once (`Salt &amp; Pepper`, never `&amp;amp;`). Pinned in Task 3 (`test_term_names_are_escaped_once`).
5. **Unlinked block on a post with no terms in that taxonomy, or with no `term` attribute:** the block renders an empty string, the same as core, with no PHP warnings. Pinned in Task 3 (`test_post_without_terms_renders_nothing`, `test_block_without_taxonomy_renders_nothing`).

---

## File Structure

```
outstand-post-terms/
├── plugin.php                       # bootstrap: guard, constants, autoload, PUC, plugins_loaded
├── composer.json                    # PSR-4 includes/, PUC runtime, PHPCS + PHPUnit dev
├── package.json                     # wp-scripts + wp-env scripts
├── webpack.config.js                # entry js/editor → src/js/editor/index.js
├── phpcs.xml.dist  phpunit.xml.dist  .wp-env.json
├── .gitignore .gitattributes .editorconfig .eslintignore
├── .github/{CODEOWNERS,FUNDING.yml,workflows/release.yml}
├── includes/
│   ├── Plugin.php                   # singleton, module list
│   ├── BaseModule.php               # register()/can_register()
│   ├── GetAssetInfo.php             # reads build/js/<entry>.asset.php
│   ├── Assets.php                   # editor script + editor iframe style
│   └── TermLinks.php                # isLink attribute + render wrapper
├── src/js/editor/
│   ├── index.js                     # entry: registers the three filters, imports styles
│   ├── constants.js                 # BLOCK_NAME, UNLINKED_CLASS
│   ├── attributes.js                # addIsLinkAttribute()
│   ├── attributes.test.js           # Jest
│   ├── controls.js                  # withTermLinksControl HOC
│   ├── preview.js                   # withUnlinkedPreview HOC
│   └── editor.scss                  # plain-text look for unlinked terms in the editor
├── tests/php/bootstrap.php
├── tests/php/unit/TermLinksTest.php
├── languages/.gitkeep
├── docs/superpowers/plans/2026-10-09-outstand-post-terms.md
└── LICENSE README.md CHANGELOG.md
```

---

### Task 1: Scaffold the plugin

**Files:**
- Create: everything in the file structure above except `includes/TermLinks.php`, `src/js/editor/{constants,attributes,attributes.test,controls,preview}.js`, `src/js/editor/editor.scss`, and `tests/php/unit/TermLinksTest.php`.
- Test: `tests/php/unit/ExampleTest.php` (temporary, deleted in Task 2).

**Interfaces:**
- Produces: `Outstand\WP\PostTerms\BaseModule` (`register(): void`, `can_register(): bool`), `Plugin::get_instance()->enable()`, `Assets::HANDLE = 'outstand-post-terms-editor'`, constants `OUTSTAND_POST_TERMS_{VERSION,BASENAME,URL,PATH,DIST_URL,DIST_PATH}`, npm scripts `build`, `test:setup`, `test:unit`, `test:js`, Composer scripts `lint`, `test`.

- [ ] **Step 1: Initialize the repository and copy the templates**

```bash
PLUGIN=/Users/s3rgiosan/www/outstand/app/public/wp-content/plugins/outstand-post-terms
TPL=~/Projects/outstand-skills/skills/outstand-plugin/templates
REF=/Users/s3rgiosan/www/outstand/app/public/wp-content/plugins/outstand-term-selector

cd "$PLUGIN"
git init -b main

cp -R "$TPL"/includes "$TPL"/src "$TPL"/tests .
cp "$TPL"/plugin.php "$TPL"/composer.json "$TPL"/package.json "$TPL"/webpack.config.js "$TPL"/phpcs.xml.dist "$TPL"/phpunit.xml.dist .
cp "$TPL"/wp-env.json .wp-env.json
cp "$TPL"/gitignore .gitignore
cp "$TPL"/gitattributes .gitattributes
cp "$TPL"/editorconfig .editorconfig
mkdir -p .github languages && cp -R "$TPL"/github/* .github/ && touch languages/.gitkeep
cp "$REF"/LICENSE "$REF"/.eslintignore .
rm includes/Logger.php
```

- [ ] **Step 2: Substitute the tokens**

```bash
cd "$PLUGIN"
grep -rl '{{' --exclude-dir=docs . | xargs sed -i '' \
  -e 's/{{TITLE}}/Outstand Post Terms/g' \
  -e 's/{{SLUG}}/outstand-post-terms/g' \
  -e 's/{{PKG}}/post-terms/g' \
  -e 's/{{NS_ESCAPED}}/Outstand\\\\WP\\\\PostTerms/g' \
  -e 's/{{NS}}/Outstand\\WP\\PostTerms/g' \
  -e 's/{{CONST}}/OUTSTAND_POST_TERMS/g' \
  -e 's/{{DESC}}/Adds a setting to the Post Terms block that lists terms without links./g' \
  -e 's/{{VERSION}}/1.0.0/g' \
  -e 's/{{REQUIRES_WP}}/7.1/g' \
  -e 's/{{ENTRY}}/editor/g'
grep -rn '{{' --exclude-dir=docs . || echo "no tokens left"
```

Expected: `no tokens left`. Open `composer.json` and confirm the PSR-4 key reads `"Outstand\\WP\\PostTerms\\": "includes/"`.

- [ ] **Step 3: Add the test tooling to `composer.json`**

Add to `composer.json` (keep the template's other keys):

```json
"autoload-dev": {
  "psr-4": {
    "Outstand\\WP\\PostTerms\\Tests\\Unit\\": "tests/php/unit/"
  }
},
"require-dev": {
  "phpunit/phpunit": "^9.6",
  "pixelalbatross/coding-standards": "dev-main",
  "yoast/phpunit-polyfills": "^1.1"
},
"scripts": {
  "lint": "phpcs .",
  "lint-fix": "phpcbf .",
  "test": "phpunit"
},
"config": {
  "sort-packages": true,
  "allow-plugins": {
    "composer/installers": true,
    "dealerdirect/phpcodesniffer-composer-installer": true
  },
  "optimize-autoloader": true,
  "platform": {
    "php": "8.2"
  }
}
```

- [ ] **Step 4: Add the test and env scripts to `package.json`**

Replace the `scripts` and `devDependencies` keys with:

```json
"scripts": {
  "start": "wp-scripts start",
  "build": "wp-scripts build",
  "packages-update": "wp-scripts packages-update",
  "lint:js": "wp-scripts lint-js",
  "lint:js:fix": "wp-scripts lint-js --fix",
  "lint:css": "wp-scripts lint-style",
  "format": "wp-scripts format",
  "wp-env": "wp-env",
  "wp-env:clean": "wp-env clean all",
  "test:setup": "npm run wp-env start",
  "test:unit": "wp-env run tests-cli --env-cwd='wp-content/plugins/outstand-post-terms' vendor/bin/phpunit --testsuite=default",
  "test:js": "wp-scripts test-unit-js"
},
"devDependencies": {
  "@wordpress/env": "^10.39.0",
  "@wordpress/scripts": "^30.12.0"
}
```

- [ ] **Step 5: Set the wp-env ports and release excludes**

In `.wp-env.json`, add after `"phpVersion": "8.2",`:

```json
"port": 8995,
"testsPort": 8996,
```

In `.gitattributes`, add under `# Directories`:

```
/docs export-ignore
/src export-ignore
/tests export-ignore
```

and under `# Files`:

```
/phpunit.xml.dist export-ignore
```

Append `.phpunit.result.cache` to the `# Project` block of `.gitignore` if the template lacks it.

- [ ] **Step 6: Write the editor entry placeholder**

Replace `src/js/editor/index.js` with:

```js
/**
 * Outstand Post Terms — editor entry.
 *
 * Adds a "Make terms links" setting to the core Post Terms block.
 *
 * Source compiles to build/js/editor.js via wp-scripts. Enqueued by the Assets
 * module with the handle "outstand-post-terms-editor".
 */
```

- [ ] **Step 7: Install, build and run the boot test**

```bash
cd "$PLUGIN"
composer install
npm install
npm run build
ls build/js/editor.js build/js/editor.asset.php
npm run test:setup
npm run test:unit
```

Expected: both build files listed; PHPUnit reports `OK (1 test, 2 assertions)` from `ExampleTest`.

- [ ] **Step 8: Lint and check guards**

```bash
composer run lint
git add -A -- . ':!vendor'
git ls-files '*.php' ':!tests' ':!vendor' | xargs grep -L "defined( 'ABSPATH' )\|defined( 'WPINC' )"
```

Expected: PHPCS clean; the guard check prints nothing. `vendor/` stays unstaged until Task 5 Step 5, so the dev toolchain never enters history.

- [ ] **Step 9: Commit**

```bash
git commit -m "chore: scaffold outstand-post-terms plugin"
```

---

### Task 2: Register the `isLink` attribute on the server

**Files:**
- Create: `includes/TermLinks.php`
- Modify: `includes/Plugin.php` (module list in `enable()`)
- Test: `tests/php/unit/TermLinksTest.php`
- Delete: `tests/php/unit/ExampleTest.php`

**Interfaces:**
- Consumes: `BaseModule` from Task 1.
- Produces: `Outstand\WP\PostTerms\TermLinks` with constants `BLOCK_NAME = 'core/post-terms'`, `TERM_CLASS = 'wp-block-post-terms__term'`, and `public function filter_block_type_args( array $args, string $block_name ): array`, hooked to `register_block_type_args` at priority 10.

- [ ] **Step 1: Write the failing tests**

Create `tests/php/unit/TermLinksTest.php`:

```php
<?php
/**
 * Tests for the Post Terms link setting.
 *
 * @package Outstand\WP\PostTerms\Tests\Unit
 */

namespace Outstand\WP\PostTerms\Tests\Unit;

use Outstand\WP\PostTerms\TermLinks;
use WP_Block_Type_Registry;

/**
 * TermLinks test case.
 */
class TermLinksTest extends \WP_UnitTestCase {

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
}
```

Delete the example test:

```bash
git rm tests/php/unit/ExampleTest.php
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `npm run test:unit`
Expected: FAIL. `test_block_registers_is_link_attribute` reports `Undefined array key "isLink"`, and the other two report `Class "Outstand\WP\PostTerms\TermLinks" not found`.

- [ ] **Step 3: Write the module**

Create `includes/TermLinks.php`:

```php
<?php

namespace Outstand\WP\PostTerms;

defined( 'ABSPATH' ) || exit;

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
	 * Add the `isLink` attribute to the Post Terms block.
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

		return $args;
	}
}
```

In `includes/Plugin.php`, replace the module list in `enable()`:

```php
		$modules = [
			new Assets(),
			new TermLinks(),
		];
```

- [ ] **Step 4: Run the tests to verify they pass**

Run: `npm run test:unit`
Expected: PASS, `OK (3 tests, 3 assertions)`.

- [ ] **Step 5: Lint and commit**

```bash
composer run lint
git add includes/TermLinks.php includes/Plugin.php tests/php/unit/TermLinksTest.php
git commit -m "feat: register an isLink attribute on the post terms block"
```

---

### Task 3: Render terms without links when `isLink` is off

**Files:**
- Modify: `includes/TermLinks.php`
- Test: `tests/php/unit/TermLinksTest.php`

**Interfaces:**
- Consumes: `TermLinks::filter_block_type_args()`, `BLOCK_NAME`, `TERM_CLASS` from Task 2.
- Produces: `public function render( callable $render_callback, array $attributes, string $content, WP_Block $block ): string` and `public function unlink_terms( array $links ): array` on `TermLinks`. `filter_block_type_args()` now also replaces `$args['render_callback']` with a closure that calls `render()`.

- [ ] **Step 1: Write the failing tests**

In `tests/php/unit/TermLinksTest.php`, add `use WP_Block;` under the existing `use` lines, then add these members. Put the constant and property at the top of the class, `set_up()`/`tear_down()` before the Task 2 tests, the new `test_*` methods after them, and the private helpers at the bottom.

```php
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
			// The exception is expected; the assertion below checks the cleanup.
			unset( $e );
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
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `npm run test:unit`
Expected: FAIL. `test_terms_render_as_spans_when_links_are_off`, `test_term_names_are_escaped_once` and `test_unlinks_links_changed_by_other_filters` fail on `<a ` still present, and `test_filter_is_removed_when_render_throws` errors with `Call to undefined method Outstand\WP\PostTerms\TermLinks::render()`. The default, keep-links and empty-output tests pass.

- [ ] **Step 3: Implement the render wrapper**

In `includes/TermLinks.php`, add `use WP_Block;` after the `defined( 'ABSPATH' ) || exit;` line. Replace `filter_block_type_args()` and add `render()` and `unlink_terms()` after it, so the class reads:

```php
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
```

- [ ] **Step 4: Run the tests to verify they pass**

Run: `npm run test:unit`
Expected: PASS, `OK (12 tests, …)` with no warnings or notices (PHPUnit converts them to failures).

- [ ] **Step 5: Lint and commit**

```bash
composer run lint
git add includes/TermLinks.php tests/php/unit/TermLinksTest.php
git commit -m "feat: render post terms without links when isLink is off"
```

---

### Task 4: Editor toggle and preview

**Files:**
- Create: `src/js/editor/constants.js`, `src/js/editor/attributes.js`, `src/js/editor/attributes.test.js`, `src/js/editor/controls.js`, `src/js/editor/preview.js`, `src/js/editor/editor.scss`
- Modify: `src/js/editor/index.js`, `includes/Assets.php`

**Interfaces:**
- Consumes: `Assets::HANDLE`, `GetAssetInfo::get_asset_info()` from Task 1; attribute name `isLink` and default `true` from Task 2.
- Produces: `addIsLinkAttribute( settings, name )`, `withTermLinksControl`, `withUnlinkedPreview`, constants `BLOCK_NAME` and `UNLINKED_CLASS`; the built stylesheet `build/js/editor.css`; `Assets::enqueue_block_assets(): void` hooked to `enqueue_block_assets`.

- [ ] **Step 1: Write the failing Jest test**

Create `src/js/editor/attributes.test.js`:

```js
/**
 * Internal dependencies
 */
import { addIsLinkAttribute } from './attributes';

describe( 'addIsLinkAttribute', () => {
	it( 'adds isLink, defaulting to true, to the Post Terms block', () => {
		const settings = addIsLinkAttribute(
			{ attributes: { term: { type: 'string' } } },
			'core/post-terms'
		);

		expect( settings.attributes.isLink ).toEqual( {
			type: 'boolean',
			default: true,
		} );
		expect( settings.attributes.term ).toEqual( { type: 'string' } );
	} );

	it( 'leaves other blocks untouched', () => {
		const settings = { attributes: {} };

		expect( addIsLinkAttribute( settings, 'core/post-title' ) ).toBe(
			settings
		);
	} );

	it( 'keeps an isLink attribute that already exists', () => {
		const settings = {
			attributes: { isLink: { type: 'boolean', default: false } },
		};

		expect( addIsLinkAttribute( settings, 'core/post-terms' ) ).toBe(
			settings
		);
	} );
} );
```

- [ ] **Step 2: Run it to verify it fails**

Run: `npm run test:js`
Expected: FAIL with `Cannot find module './attributes'`.

- [ ] **Step 3: Write the constants and the attribute filter**

Create `src/js/editor/constants.js`:

```js
export const BLOCK_NAME = 'core/post-terms';

export const UNLINKED_CLASS = 'outstand-post-terms-unlinked';
```

Create `src/js/editor/attributes.js`:

```js
/**
 * Internal dependencies
 */
import { BLOCK_NAME } from './constants';

/**
 * Adds the `isLink` attribute to the Post Terms block. A core `isLink`
 * attribute takes precedence when one exists.
 *
 * @param {Object} settings Block settings.
 * @param {string} name     Block name.
 * @return {Object} Block settings.
 */
export function addIsLinkAttribute( settings, name ) {
	if ( name !== BLOCK_NAME || settings.attributes?.isLink ) {
		return settings;
	}

	return {
		...settings,
		attributes: {
			...settings.attributes,
			isLink: {
				type: 'boolean',
				default: true,
			},
		},
	};
}
```

- [ ] **Step 4: Run the Jest test to verify it passes**

Run: `npm run test:js`
Expected: PASS, 3 tests.

- [ ] **Step 5: Write the inspector toggle**

Create `src/js/editor/controls.js`:

```js
/**
 * WordPress dependencies
 */
import { InspectorControls } from '@wordpress/block-editor';
import {
	ToggleControl,
	__experimentalToolsPanel as ToolsPanel, // eslint-disable-line @wordpress/no-unsafe-wp-apis
	__experimentalToolsPanelItem as ToolsPanelItem, // eslint-disable-line @wordpress/no-unsafe-wp-apis
} from '@wordpress/components';
import { createHigherOrderComponent } from '@wordpress/compose';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { BLOCK_NAME } from './constants';

/**
 * Adds a "Make terms links" toggle to the Post Terms block inspector.
 */
export const withTermLinksControl = createHigherOrderComponent(
	( BlockEdit ) => ( props ) => {
		if ( props.name !== BLOCK_NAME ) {
			return <BlockEdit { ...props } />;
		}

		const { attributes, setAttributes } = props;
		const isLink = attributes.isLink !== false;

		return (
			<>
				<BlockEdit { ...props } />
				<InspectorControls>
					<ToolsPanel
						label={ __( 'Settings', 'outstand-post-terms' ) }
						resetAll={ () => setAttributes( { isLink: true } ) }
					>
						<ToolsPanelItem
							label={ __( 'Make terms links', 'outstand-post-terms' ) }
							isShownByDefault
							hasValue={ () => ! isLink }
							onDeselect={ () => setAttributes( { isLink: true } ) }
						>
							<ToggleControl
								label={ __(
									'Make terms links',
									'outstand-post-terms'
								) }
								checked={ isLink }
								onChange={ () =>
									setAttributes( { isLink: ! isLink } )
								}
							/>
						</ToolsPanelItem>
					</ToolsPanel>
				</InspectorControls>
			</>
		);
	},
	'withTermLinksControl'
);
```

- [ ] **Step 6: Write the editor preview class and styles**

Create `src/js/editor/preview.js`:

```js
/**
 * WordPress dependencies
 */
import { createHigherOrderComponent } from '@wordpress/compose';

/**
 * Internal dependencies
 */
import { BLOCK_NAME, UNLINKED_CLASS } from './constants';

/**
 * Adds a class to unlinked Post Terms blocks so the editor shows their terms
 * as plain text.
 */
export const withUnlinkedPreview = createHigherOrderComponent(
	( BlockListBlock ) => ( props ) => {
		if ( props.name !== BLOCK_NAME || props.attributes.isLink !== false ) {
			return <BlockListBlock { ...props } />;
		}

		const className = [ props.className, UNLINKED_CLASS ]
			.filter( Boolean )
			.join( ' ' );

		return <BlockListBlock { ...props } className={ className } />;
	},
	'withUnlinkedPreview'
);
```

Create `src/js/editor/editor.scss`:

```scss
// The editor renders each term as a link; unlinked blocks show them as text.
.wp-block-post-terms.outstand-post-terms-unlinked a {
	color: inherit;
	text-decoration: none;
	pointer-events: none;
}
```

- [ ] **Step 7: Wire the entry**

Replace `src/js/editor/index.js` with:

```js
/**
 * Outstand Post Terms — editor entry.
 *
 * Adds a "Make terms links" setting to the core Post Terms block.
 *
 * Source compiles to build/js/editor.js via wp-scripts. Enqueued by the Assets
 * module with the handle "outstand-post-terms-editor".
 */

/**
 * WordPress dependencies
 */
import { addFilter } from '@wordpress/hooks';

/**
 * Internal dependencies
 */
import { addIsLinkAttribute } from './attributes';
import { withTermLinksControl } from './controls';
import { withUnlinkedPreview } from './preview';
import './editor.scss';

addFilter(
	'blocks.registerBlockType',
	'outstand/post-terms/attributes',
	addIsLinkAttribute
);

addFilter(
	'editor.BlockEdit',
	'outstand/post-terms/controls',
	withTermLinksControl
);

addFilter(
	'editor.BlockListBlock',
	'outstand/post-terms/preview',
	withUnlinkedPreview
);
```

- [ ] **Step 8: Load the stylesheet into the editor iframe**

In `includes/Assets.php`, add the hook in `register()` after the existing `add_action`:

```php
		add_action( 'enqueue_block_assets', [ $this, 'enqueue_block_assets' ] );
```

and add this method after `enqueue_block_editor_scripts()`:

```php
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
```

- [ ] **Step 9: Build and lint**

```bash
npm run build
ls build/js/editor.js build/js/editor.css build/js/editor.asset.php
grep -o "wp-block-editor\|wp-components\|wp-compose\|wp-hooks\|wp-i18n" build/js/editor.asset.php | sort -u
npm run lint:js
npm run lint:css
composer run lint
npm run test:js
```

Expected: all three build files exist; the asset file lists `wp-block-editor`, `wp-components`, `wp-compose`, `wp-hooks`, `wp-i18n`; every lint is clean; Jest passes.

- [ ] **Step 10: Commit**

```bash
git add src/js/editor includes/Assets.php build
git commit -m "feat: add the term links toggle and unlinked preview to the editor"
```

---

### Task 5: Docs, browser verification and release prep

**Files:**
- Create: `README.md`, `CHANGELOG.md`
- Modify: `~/Projects/outstand-skills/skills/outstand-plugin/SKILL.md` (storage-token registry; separate repo and commit)

**Interfaces:**
- Consumes: everything above.
- Produces: a releasable `1.0.0` working tree (not tagged).

- [ ] **Step 1: Write `README.md`**

````markdown
# Outstand Post Terms

Adds a setting to the Post Terms block that lists terms without links.

## Why

The core Post Terms block (and its Categories and Tags variations) links every
term to its archive page. On sites with no taxonomy archive templates, those
links lead nowhere useful. This plugin adds a **Make terms links** toggle to the
block's Settings panel. Turn it off and the block lists the post's terms as plain
text.

## Markup

With links off, each term renders as a span in the position of the link:

```html
<div class="taxonomy-category wp-block-post-terms">
	<span class="wp-block-post-terms__term">News</span><span class="wp-block-post-terms__separator">, </span><span class="wp-block-post-terms__term">Events</span>
</div>
```

Style unlinked terms with `.wp-block-post-terms__term`.

Existing blocks keep their links: the setting defaults to on.

## Requirements

- WordPress 7.1+
- PHP 8.2+
- The taxonomy must be publicly queryable. Core's Post Terms block renders
  nothing for a taxonomy registered with `publicly_queryable => false`.

## Development

```sh
composer install
npm install
npm run build          # → build/js/editor.js, editor.css, editor.asset.php
```

Linting:

```sh
composer run lint      # phpcs (PixelAlbatross)
npm run lint:js
npm run lint:css
```

Tests:

```sh
npm run test:js        # Jest
npm run test:setup     # starts wp-env (ports 8995 / 8996), Docker required
npm run test:unit      # PHPUnit integration tests
```

Before tagging a release, rebuild the committed `vendor/` without dev
dependencies:

```sh
composer update --no-dev -o
```

## License

GPL-3.0-or-later. See [LICENSE](LICENSE).
````

- [ ] **Step 2: Write `CHANGELOG.md`**

Start from the skill's `templates/CHANGELOG.md`; the result reads:

```markdown
# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - YYYY-MM-DD

### Added

- A "Make terms links" setting on the Post Terms block that lists the post's terms as plain text when turned off.

[Unreleased]: https://github.com/pixelalbatross/outstand-post-terms/compare/1.0.0...HEAD
[1.0.0]: https://github.com/pixelalbatross/outstand-post-terms/releases/tag/1.0.0
```

Replace `YYYY-MM-DD` with the release date when tagging.

- [ ] **Step 3: Verify in the browser on `https://outstand.test`**

1. In Local, open the site shell for `outstand` and run `wp plugin activate outstand-post-terms`. Expected: `Plugin 'outstand-post-terms' activated.`
2. Log in at `https://outstand.test/wp-admin` (`admin` / `password`; if rejected, `snapshots` / `password`).
3. Open a post that has at least two categories. Add a **Categories** block (a Post Terms variation) and select it.
4. Expected in the inspector: a **Settings** tools panel with **Make terms links** on. Turn it off. Expected: the panel's options menu shows **Reset**; using it turns the toggle back on. Turn it off again.
5. Expected in the canvas: terms show in the surrounding text color, without underline, and clicking a term selects the block.
6. Save and view the post. Expected in the page source: `<span class="wp-block-post-terms__term">` per term, no `<a` inside `.wp-block-post-terms`, separators unchanged.
7. Add a second Categories block with the toggle left on. Expected: that block still renders links on the front end.
8. Check the browser console in the editor: no errors or block validation warnings.

- [ ] **Step 4: Run the full verification checklist**

```bash
cd "$PLUGIN"
npm run test:js && npm run test:unit
composer run lint && npm run lint:js && npm run lint:css
npx editorconfig-checker -exclude '^LICENSE$|^build/|^vendor/'
git ls-files '*.php' ':!tests' ':!vendor' | xargs grep -L "defined( 'ABSPATH' )\|defined( 'WPINC' )"
grep -n "Version:" plugin.php; grep -n "_VERSION'" plugin.php; grep -n '"version"' package.json
```

Expected: tests and lints pass; the editorconfig check and guard check print nothing; all three version lines read `1.0.0`.

- [ ] **Step 5: Slim `vendor/` and commit the plugin**

```bash
git add README.md CHANGELOG.md
git commit -m "docs: add readme and changelog"

composer update --no-dev -o
ls vendor/bin 2>/dev/null || echo "no dev binaries"
git add vendor composer.lock
git commit -m "chore: add runtime vendor"
```

Expected: `no dev binaries`; `vendor/yahnis-elsts/plugin-update-checker` still present. This is the first commit that contains `vendor/`. Run `composer install` again before further development to restore the dev tools.

- [ ] **Step 6: Reserve the test token in the house standard**

In `~/Projects/outstand-skills/skills/outstand-plugin/SKILL.md`, add a row to the storage-token registry table, after the `ost` row:

```markdown
  | `ospt` | outstand-post-terms | test fixtures only; reserved |
```

Leave the change uncommitted when that repo already has other uncommitted edits to `SKILL.md`; otherwise commit it with `docs(outstand-plugin): reserve the ospt storage token`.
