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
