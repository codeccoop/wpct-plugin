<?php
/**
 * Test setting schema.
 *
 * @package wpct-plugin-tests
 */

return array(
	'name'       => 'store-test-case',
	'properties' => array(
		'foo'     => array(
			'type'    => 'string',
			'default' => 'bar',
			'enum'    => array( 'bar', 'boo', 'foo' ),
		),
		'email'   => array(
			'type'    => 'string',
			'format'  => 'email',
			'default' => 'johndoe@email.me',
		),
		'serie'   => array(
			'type'        => 'array',
			'items'       => array(
				'type'    => 'integer',
				'min'     => 0,
				'max'     => 10,
				'default' => 0,
			),
			'uniqueItems' => true,
			'minItems'    => 1,
			'maxItems'    => 8,
		),
		'address' => array(
			'type'       => 'object',
			'properties' => array(
				'street'  => array( 'type' => 'string' ),
				'zip'     => array( 'type' => 'string' ),
				'state'   => array( 'type' => 'string' ),
				'country' => array(
					'type'    => 'string',
					'default' => 'ES',
				),
			),
			'required'   => array(
				'street',
				'zip',
				'state',
				'country',
			),
		),
	),
	'required'   => array(
		'foo',
		'email',
		'serie',
	),
	'default'    => array(
		'foo'   => 'bar',
		'email' => 'johndoe@email.me',
		'serie' => array( 1, 2, 3, 4, 5 ),
	),
);
