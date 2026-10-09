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
