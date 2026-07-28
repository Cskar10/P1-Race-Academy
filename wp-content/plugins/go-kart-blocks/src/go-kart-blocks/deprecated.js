import { RichText, useBlockProps } from '@wordpress/block-editor';

const attributes = {
	prompt: {
		type: 'string',
		default:
			'What is one thing you would apply differently after this lesson?',
	},
	responseId: {
		type: 'string',
		default: '',
	},
};

const v2 = {
	attributes,
	save( props ) {
		const { prompt, responseId } = props.attributes;

		return (
			<section
				{ ...useBlockProps.save() }
				data-response-id={ responseId }
			>
				<p className="p1-reflection-prompt__eyebrow">
					{ 'Reflection prompt' }
				</p>
				<RichText.Content
					tagName="h3"
					className="p1-reflection-prompt__question"
					value={ prompt }
				/>
				<p className="p1-reflection-prompt__label">
					{ 'Your response' }
				</p>
				<textarea
					className="p1-reflection-prompt__response"
					rows="5"
					aria-label="Your response"
					placeholder="Write your reflection here."
				/>
				<button className="p1-reflection-prompt__save" type="button">
					{ 'Save response' }
				</button>
				<p className="p1-reflection-prompt__status">
					{ 'Loading your saved response…' }
				</p>
			</section>
		);
	},
};

export default [ v2 ];
