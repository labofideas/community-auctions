/**
 * Auction Grid Block
 *
 * Displays a grid or list of auctions.
 */

import { registerBlockType } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';
import Edit from './edit';
import metadata from './block.json';

import './style.css';

registerBlockType( metadata.name, {
	...metadata,
	edit: Edit,
	save: () => null, // Server-side rendered.
} );
