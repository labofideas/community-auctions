/**
 * Countdown Timer - Edit Component
 */

import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import {
	PanelBody,
	SelectControl,
	ToggleControl,
	TextControl,
	RangeControl,
	Placeholder,
	Spinner,
	ComboboxControl,
} from '@wordpress/components';
import { useState, useEffect } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import ServerSideRender from '@wordpress/server-side-render';

export default function Edit( { attributes, setAttributes } ) {
	const {
		auctionId,
		style,
		showLabels,
		showSeconds,
		endedText,
		urgentThreshold,
	} = attributes;

	const [ auctions, setAuctions ] = useState( [] );
	const [ isLoading, setIsLoading ] = useState( true );

	const blockProps = useBlockProps();

	// Fetch auctions for the selector.
	useEffect( () => {
		setIsLoading( true );
		apiFetch( {
			path: '/wp/v2/auction?per_page=100&status=publish,ca_live',
		} )
			.then( ( posts ) => {
				const options = posts.map( ( post ) => ( {
					value: post.id,
					label: post.title.rendered,
				} ) );
				setAuctions( options );
				setIsLoading( false );
			} )
			.catch( () => {
				setIsLoading( false );
			} );
	}, [] );

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Auction Selection', 'community-auctions' ) }>
					{ isLoading ? (
						<Spinner />
					) : (
						<ComboboxControl
							label={ __( 'Select Auction', 'community-auctions' ) }
							value={ auctionId }
							options={ auctions }
							onChange={ ( value ) => setAttributes( { auctionId: parseInt( value, 10 ) || 0 } ) }
							help={ __( 'Choose an auction to display the countdown for.', 'community-auctions' ) }
						/>
					) }
				</PanelBody>

				<PanelBody title={ __( 'Display Settings', 'community-auctions' ) }>
					<SelectControl
						label={ __( 'Style', 'community-auctions' ) }
						value={ style }
						options={ [
							{ label: __( 'Default', 'community-auctions' ), value: 'default' },
							{ label: __( 'Compact', 'community-auctions' ), value: 'compact' },
							{ label: __( 'Large', 'community-auctions' ), value: 'large' },
							{ label: __( 'Minimal', 'community-auctions' ), value: 'minimal' },
						] }
						onChange={ ( value ) => setAttributes( { style: value } ) }
					/>
					<ToggleControl
						label={ __( 'Show Labels', 'community-auctions' ) }
						checked={ showLabels }
						onChange={ ( value ) => setAttributes( { showLabels: value } ) }
						help={ __( 'Show labels like "Days", "Hours", etc.', 'community-auctions' ) }
					/>
					<ToggleControl
						label={ __( 'Show Seconds', 'community-auctions' ) }
						checked={ showSeconds }
						onChange={ ( value ) => setAttributes( { showSeconds: value } ) }
					/>
					<TextControl
						label={ __( 'Ended Text', 'community-auctions' ) }
						value={ endedText }
						onChange={ ( value ) => setAttributes( { endedText: value } ) }
						help={ __( 'Text to show when the auction has ended.', 'community-auctions' ) }
					/>
					<RangeControl
						label={ __( 'Urgent Threshold (minutes)', 'community-auctions' ) }
						value={ urgentThreshold }
						onChange={ ( value ) => setAttributes( { urgentThreshold: value } ) }
						min={ 5 }
						max={ 1440 }
						help={ __( 'Add urgent styling when less than this many minutes remain.', 'community-auctions' ) }
					/>
				</PanelBody>
			</InspectorControls>

			<div { ...blockProps }>
				{ ! auctionId ? (
					<Placeholder
						icon="clock"
						label={ __( 'Auction Countdown', 'community-auctions' ) }
					>
						{ __( 'Select an auction from the block settings.', 'community-auctions' ) }
					</Placeholder>
				) : (
					<ServerSideRender
						block="community-auctions/countdown-timer"
						attributes={ attributes }
						LoadingResponsePlaceholder={ () => (
							<Placeholder icon="clock" label={ __( 'Auction Countdown', 'community-auctions' ) }>
								<Spinner />
							</Placeholder>
						) }
						ErrorResponsePlaceholder={ () => (
							<Placeholder icon="warning" label={ __( 'Error', 'community-auctions' ) }>
								{ __( 'Error loading countdown. Please check your settings.', 'community-auctions' ) }
							</Placeholder>
						) }
					/>
				) }
			</div>
		</>
	);
}
