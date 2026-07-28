/**
 * Registers a new block provided a unique name and an object defining its behavior.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/block-api/block-registration/
 */
import { registerBlockType } from '@wordpress/blocks';
import { dispatch, select, subscribe } from '@wordpress/data';

/**
 * Lets webpack process CSS, SASS or SCSS files referenced in JavaScript files.
 * All files containing `style` keyword are bundled together. The code used
 * gets applied both to the front of your site and to the editor.
 *
 * @see https://www.npmjs.com/package/@wordpress/scripts#using-css
 */
import './style.scss';

/**
 * Internal dependencies
 */
import Edit from './edit';
import deprecated from './deprecated';
import save from './save';
import metadata from './block.json';

/**
 * Every block starts by registering a new block type definition.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/block-api/block-registration/
 */
registerBlockType( metadata.name, {
	/**
	 * @see ./edit.js
	 */
	edit: Edit,

	deprecated,

	/**
	 * @see ./save.js
	 */
	save,
} );

let isNormalizingResponseIds = false;

/**
 * Keeps every Reflection Prompt response ID unique inside the editor.
 *
 * Duplicating or pasting a block copies its attributes. Without this editor-wide
 * check, two prompts could otherwise save into the same student response slot.
 */
subscribe( () => {
	if ( isNormalizingResponseIds ) {
		return;
	}

	const blockEditor = select( 'core/block-editor' );
	if ( ! blockEditor?.getBlocksByName ) {
		return;
	}

	const seenResponseIds = new Set();
	const updates = [];

	blockEditor.getBlocksByName( metadata.name ).forEach( ( clientId ) => {
		const responseId =
			blockEditor.getBlockAttributes( clientId )?.responseId || '';

		if ( ! responseId || seenResponseIds.has( responseId ) ) {
			const uniqueResponseId = `reflection-${ clientId }`;
			updates.push( { clientId, responseId: uniqueResponseId } );
			seenResponseIds.add( uniqueResponseId );
			return;
		}

		seenResponseIds.add( responseId );
	} );

	if ( ! updates.length ) {
		return;
	}

	isNormalizingResponseIds = true;
	const blockEditorDispatch = dispatch( 'core/block-editor' );
	updates.forEach( ( { clientId, responseId } ) => {
		blockEditorDispatch.updateBlockAttributes( clientId, { responseId } );
	} );
	isNormalizingResponseIds = false;
} );
