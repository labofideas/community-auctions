/**
 * Auction Grid - Edit Component
 */

import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import {
	PanelBody,
	RangeControl,
	SelectControl,
	ToggleControl,
	Placeholder,
	Spinner,
} from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';

export default function Edit( { attributes, setAttributes } ) {
	const {
		columns,
		postsPerPage,
		layout,
		status,
		orderBy,
		order,
		showCountdown,
		showBidCount,
		showCurrentBid,
		showImage,
		showCategory,
	} = attributes;

	const blockProps = useBlockProps();

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Layout Settings', 'community-auctions' ) }>
					<SelectControl
						label={ __( 'Layout', 'community-auctions' ) }
						value={ layout }
						options={ [
							{ label: __( 'Grid', 'community-auctions' ), value: 'grid' },
							{ label: __( 'List', 'community-auctions' ), value: 'list' },
						] }
						onChange={ ( value ) => setAttributes( { layout: value } ) }
					/>
					{ layout === 'grid' && (
						<RangeControl
							label={ __( 'Columns', 'community-auctions' ) }
							value={ columns }
							onChange={ ( value ) => setAttributes( { columns: value } ) }
							min={ 1 }
							max={ 4 }
						/>
					) }
					<RangeControl
						label={ __( 'Number of Auctions', 'community-auctions' ) }
						value={ postsPerPage }
						onChange={ ( value ) => setAttributes( { postsPerPage: value } ) }
						min={ 1 }
						max={ 12 }
					/>
				</PanelBody>

				<PanelBody title={ __( 'Filter & Sort', 'community-auctions' ) }>
					<SelectControl
						label={ __( 'Status', 'community-auctions' ) }
						value={ status }
						options={ [
							{ label: __( 'Live Auctions', 'community-auctions' ), value: 'live' },
							{ label: __( 'Ended Auctions', 'community-auctions' ), value: 'ended' },
							{ label: __( 'Upcoming', 'community-auctions' ), value: 'upcoming' },
							{ label: __( 'All', 'community-auctions' ), value: 'all' },
						] }
						onChange={ ( value ) => setAttributes( { status: value } ) }
					/>
					<SelectControl
						label={ __( 'Order By', 'community-auctions' ) }
						value={ orderBy }
						options={ [
							{ label: __( 'Date', 'community-auctions' ), value: 'date' },
							{ label: __( 'End Time', 'community-auctions' ), value: 'end_time' },
							{ label: __( 'Current Bid', 'community-auctions' ), value: 'current_bid' },
							{ label: __( 'Bid Count', 'community-auctions' ), value: 'bid_count' },
							{ label: __( 'Title', 'community-auctions' ), value: 'title' },
						] }
						onChange={ ( value ) => setAttributes( { orderBy: value } ) }
					/>
					<SelectControl
						label={ __( 'Order', 'community-auctions' ) }
						value={ order }
						options={ [
							{ label: __( 'Descending', 'community-auctions' ), value: 'DESC' },
							{ label: __( 'Ascending', 'community-auctions' ), value: 'ASC' },
						] }
						onChange={ ( value ) => setAttributes( { order: value } ) }
					/>
				</PanelBody>

				<PanelBody title={ __( 'Display Options', 'community-auctions' ) } initialOpen={ false }>
					<ToggleControl
						label={ __( 'Show Image', 'community-auctions' ) }
						checked={ showImage }
						onChange={ ( value ) => setAttributes( { showImage: value } ) }
					/>
					<ToggleControl
						label={ __( 'Show Countdown', 'community-auctions' ) }
						checked={ showCountdown }
						onChange={ ( value ) => setAttributes( { showCountdown: value } ) }
					/>
					<ToggleControl
						label={ __( 'Show Current Bid', 'community-auctions' ) }
						checked={ showCurrentBid }
						onChange={ ( value ) => setAttributes( { showCurrentBid: value } ) }
					/>
					<ToggleControl
						label={ __( 'Show Bid Count', 'community-auctions' ) }
						checked={ showBidCount }
						onChange={ ( value ) => setAttributes( { showBidCount: value } ) }
					/>
					<ToggleControl
						label={ __( 'Show Category', 'community-auctions' ) }
						checked={ showCategory }
						onChange={ ( value ) => setAttributes( { showCategory: value } ) }
					/>
				</PanelBody>
			</InspectorControls>

			<div { ...blockProps }>
				<ServerSideRender
					block="community-auctions/auction-grid"
					attributes={ attributes }
					LoadingResponsePlaceholder={ () => (
						<Placeholder icon="grid-view" label={ __( 'Auction Grid', 'community-auctions' ) }>
							<Spinner />
						</Placeholder>
					) }
					ErrorResponsePlaceholder={ () => (
						<Placeholder icon="warning" label={ __( 'Error', 'community-auctions' ) }>
							{ __( 'Error loading auctions. Please check your settings.', 'community-auctions' ) }
						</Placeholder>
					) }
					EmptyResponsePlaceholder={ () => (
						<Placeholder icon="grid-view" label={ __( 'Auction Grid', 'community-auctions' ) }>
							{ __( 'No auctions found matching your criteria.', 'community-auctions' ) }
						</Placeholder>
					) }
				/>
			</div>
		</>
	);
}
