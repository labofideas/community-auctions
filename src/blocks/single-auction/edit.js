/**
 * Single Auction - Edit Component
 */

import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import {
	PanelBody,
	ToggleControl,
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
		showImage,
		showGallery,
		showCountdown,
		showBidHistory,
		showBidForm,
		showSellerInfo,
		showCategory,
		showBuyNow,
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
							help={ __( 'Choose an auction to display.', 'community-auctions' ) }
						/>
					) }
				</PanelBody>

				<PanelBody title={ __( 'Display Options', 'community-auctions' ) } initialOpen={ false }>
					<ToggleControl
						label={ __( 'Show Featured Image', 'community-auctions' ) }
						checked={ showImage }
						onChange={ ( value ) => setAttributes( { showImage: value } ) }
					/>
					<ToggleControl
						label={ __( 'Show Gallery', 'community-auctions' ) }
						checked={ showGallery }
						onChange={ ( value ) => setAttributes( { showGallery: value } ) }
					/>
					<ToggleControl
						label={ __( 'Show Countdown', 'community-auctions' ) }
						checked={ showCountdown }
						onChange={ ( value ) => setAttributes( { showCountdown: value } ) }
					/>
					<ToggleControl
						label={ __( 'Show Bid History', 'community-auctions' ) }
						checked={ showBidHistory }
						onChange={ ( value ) => setAttributes( { showBidHistory: value } ) }
					/>
					<ToggleControl
						label={ __( 'Show Bid Form', 'community-auctions' ) }
						checked={ showBidForm }
						onChange={ ( value ) => setAttributes( { showBidForm: value } ) }
					/>
					<ToggleControl
						label={ __( 'Show Seller Info', 'community-auctions' ) }
						checked={ showSellerInfo }
						onChange={ ( value ) => setAttributes( { showSellerInfo: value } ) }
					/>
					<ToggleControl
						label={ __( 'Show Category', 'community-auctions' ) }
						checked={ showCategory }
						onChange={ ( value ) => setAttributes( { showCategory: value } ) }
					/>
					<ToggleControl
						label={ __( 'Show Buy Now', 'community-auctions' ) }
						checked={ showBuyNow }
						onChange={ ( value ) => setAttributes( { showBuyNow: value } ) }
					/>
				</PanelBody>
			</InspectorControls>

			<div { ...blockProps }>
				{ ! auctionId ? (
					<Placeholder
						icon="money-alt"
						label={ __( 'Single Auction', 'community-auctions' ) }
					>
						{ __( 'Select an auction from the block settings.', 'community-auctions' ) }
					</Placeholder>
				) : (
					<ServerSideRender
						block="community-auctions/single-auction"
						attributes={ attributes }
						LoadingResponsePlaceholder={ () => (
							<Placeholder icon="money-alt" label={ __( 'Single Auction', 'community-auctions' ) }>
								<Spinner />
							</Placeholder>
						) }
						ErrorResponsePlaceholder={ () => (
							<Placeholder icon="warning" label={ __( 'Error', 'community-auctions' ) }>
								{ __( 'Error loading auction. Please check your settings.', 'community-auctions' ) }
							</Placeholder>
						) }
					/>
				) }
			</div>
		</>
	);
}
