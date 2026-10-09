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

Style unlinked terms with `.wp-block-post-terms__term`. The block's link color
setting has no effect on unlinked terms; they use the block's text color.

Existing blocks keep their links: the setting defaults to on.

## Requirements

- WordPress 7.1+
- PHP 8.2+
- The taxonomy must be publicly queryable. Core's Post Terms block renders nothing for a taxonomy registered with `publicly_queryable => false`.

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
