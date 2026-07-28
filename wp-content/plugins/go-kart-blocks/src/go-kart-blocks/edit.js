/**
 * Retrieves the translation of text.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/packages/packages-i18n/
 */
import { __ } from '@wordpress/i18n';

/**
 * React hook that is used to mark the block wrapper element.
 * It provides all the necessary props like the class name.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/packages/packages-block-editor/#useblockprops
 */
import { RichText, useBlockProps } from '@wordpress/block-editor';

/**
 * Lets webpack process CSS, SASS or SCSS files referenced in JavaScript files.
 * Those files can contain any CSS code that gets applied to the editor.
 *
 * @see https://www.npmjs.com/package/@wordpress/scripts#using-css
 */
import './editor.scss';

/**
 * The edit function describes the structure of your block in the context of the
 * editor. This represents what the editor will render when the block is used.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/block-api/block-edit-save/#edit
 *
 * @param {Object}   props               Block editor properties.
 * @param {Object}   props.attributes    Saved block attributes.
 * @param {Function} props.setAttributes Updates saved block attributes.
 * @param {string}   props.clientId      Stable editor instance ID.
 *
 * @return {Element} Element to render.
 */
export default function Edit( props ) {
	const { attributes, setAttributes } = props;
	const { prompt } = attributes;

	return (
		<section { ...useBlockProps() }>
			<p className="p1-reflection-prompt__eyebrow">
				{ __( 'Reflection prompt', 'go-kart-blocks' ) }
			</p>
			<RichText
				tagName="h3"
				className="p1-reflection-prompt__question"
				value={ prompt }
				onChange={ ( value ) => setAttributes( { prompt: value } ) }
				placeholder={ __(
					'Write the reflection question…',
					'go-kart-blocks'
				) }
				allowedFormats={ [] }
			/>
			<p className="p1-reflection-prompt__label">
				{ __( 'Student response', 'go-kart-blocks' ) }
			</p>
			<textarea
				className="p1-reflection-prompt__response"
				rows="5"
				aria-label={ __(
					'Student response preview',
					'go-kart-blocks'
				) }
				placeholder={ __(
					'Students will type their response here.',
					'go-kart-blocks'
				) }
				disabled
			/>
			<p className="p1-reflection-prompt__editor-note">
				{ __(
					'Each logged-in student’s response is saved privately to their account.',
					'go-kart-blocks'
				) }
			</p>
		</section>
	);
}
