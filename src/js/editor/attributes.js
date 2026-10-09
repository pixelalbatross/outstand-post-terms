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
