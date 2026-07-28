/**
 * React hook that is used to mark the block wrapper element.
 * It provides all the necessary props like the class name.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/packages/packages-block-editor/#useblockprops
 */
import { RichText, useBlockProps } from '@wordpress/block-editor';

/**
 * The save function defines the way in which the different attributes should
 * be combined into the final markup, which is then serialized by the block
 * editor into `post_content`.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/block-api/block-edit-save/#save
 *
 * @param {Object} props            Block save properties.
 * @param {Object} props.attributes Saved block attributes.
 *
 * @return {Element} Element to render.
 */
export default function save( props ) {
	const { attributes } = props;
	const { prompt, responseId } = attributes;

	return (
		<section { ...useBlockProps.save() } data-response-id={ responseId }>
			<p className="p1-reflection-prompt__eyebrow">
				{ 'Reflection prompt' }
			</p>
			<RichText.Content
				tagName="h3"
				className="p1-reflection-prompt__question"
				value={ prompt }
			/>
			<p className="p1-reflection-prompt__label">{ 'Your response' }</p>
			<textarea
				className="p1-reflection-prompt__response"
				rows="5"
				aria-label="Your response"
				placeholder="Write your reflection here."
			/>
			<button className="p1-reflection-prompt__save" type="button">
				{ 'Save response' }
			</button>
			<p
				className="p1-reflection-prompt__status"
				role="status"
				aria-live="polite"
			>
				{ 'Loading your saved response…' }
			</p>
		</section>
	);
}
