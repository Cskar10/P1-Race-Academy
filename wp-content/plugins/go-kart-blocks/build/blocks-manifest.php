<?php
// This file is generated. Do not modify it manually.
return array(
	'go-kart-blocks' => array(
		'$schema' => 'https://schemas.wp.org/trunk/block.json',
		'apiVersion' => 3,
		'name' => 'p1-race-academy/reflection-prompt',
		'version' => '0.3.0',
		'title' => 'Reflection Prompt',
		'category' => 'text',
		'icon' => 'edit-page',
		'description' => 'A configurable reflection question for Sensei lessons.',
		'attributes' => array(
			'prompt' => array(
				'type' => 'string',
				'default' => 'What is one thing you would apply differently after this lesson?'
			),
			'responseId' => array(
				'type' => 'string',
				'default' => ''
			)
		),
		'example' => array(
			'attributes' => array(
				'prompt' => 'What is the most useful idea from this lesson?'
			)
		),
		'supports' => array(
			'html' => false
		),
		'textdomain' => 'go-kart-blocks',
		'editorScript' => 'file:./index.js',
		'editorStyle' => 'file:./index.css',
		'style' => 'file:./style-index.css',
		'viewScript' => 'file:./view.js'
	)
);
