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
