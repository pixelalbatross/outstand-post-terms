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
							label={ __(
								'Make terms links',
								'outstand-post-terms'
							) }
							isShownByDefault
							hasValue={ () => ! isLink }
							onDeselect={ () =>
								setAttributes( { isLink: true } )
							}
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
