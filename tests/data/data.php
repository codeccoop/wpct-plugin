<?php

$wpct_plugin_test_error = WP_Error::class;

return array(
	array( 1, array( 'type' => 'integer' ), 1 ),
	array( 28.9, array( 'type' => 'number' ), 28.9 ),
	array( 28.9, array( 'type' => 'integer' ), 28 ),
	array(
		10,
		array(
			'type' => 'number',
			'max'  => 5,
		),
		$wpct_plugin_test_error,
	),
	array(
		10,
		array(
			'type' => 'number',
			'min'  => 15,
		),
		$wpct_plugin_test_error,
	),
	array( 'foo', array( 'type' => 'string' ), 'foo' ),
	array( 10, array( 'type' => 'string' ), $wpct_plugin_test_error ),
	array( 10, array( 'type' => array( 'string', 'integer' ) ), 10 ),
	array(
		'lucas@example.coop',
		array(
			'type'   => 'string',
			'format' => 'email',
		),
		'lucas@example.coop',
	),
	array(
		'lucas',
		array(
			'type'   => 'string',
			'format' => 'email',
		),
		$wpct_plugin_test_error,
	),
	array(
		'foo',
		array(
			'type'   => 'string',
			'format' => 'uri',
		),
		'http://foo',
	),
	array(
		'https://www.codeccoop.org',
		array(
			'type'   => 'string',
			'format' => 'uri',
		),
		'https://www.codeccoop.org',
	),
	array(
		'A',
		array(
			'type' => 'string',
			'enum' => array( 'A', 'B', 'C' ),
		),
		'A',
	),
	array(
		'D',
		array(
			'type' => 'string',
			'enum' => array( 'A', 'B', 'C' ),
		),
		$wpct_plugin_test_error,
	),
	array(
		'D',
		array(
			'type'    => 'string',
			'enum'    => array( 'A', 'B', 'C' ),
			'default' => 'A',
		),
		'A',
	),
	array(
		'Lorem ipsum dolor sit amer',
		array(
			'type'      => 'string',
			'maxLength' => 5,
		),
		$wpct_plugin_test_error,
	),
	array(
		array( 1, 2, 3, 4 ),
		array(
			'type'  => 'array',
			'items' => array( 'type' => 'integer' ),
		),
		array( 1, 2, 3, 4 ),
	),
	array(
		array( 1, 2, 3, 4 ),
		array(
			'type'     => 'array',
			'items'    => array( 'type' => 'integer' ),
			'minItems' => 5,
		),
		$wpct_plugin_test_error,
	),
	array(
		array( 1, 2, 3, 4 ),
		array(
			'type'     => 'array',
			'items'    => array( 'type' => 'integer' ),
			'maxItems' => 3,
		),
		$wpct_plugin_test_error,
	),
	array(
		array( 1, 2, 3, 4 ),
		array(
			'type'     => 'array',
			'items'    => array( 'type' => 'integer' ),
			'minItems' => 5,
		),
		$wpct_plugin_test_error,
	),
	array(
		array( 1, 2, 3, 4 ),
		array(
			'type'  => 'array',
			'items' => array(
				'type' => 'integer',
				'min'  => 5,
			),
		),
		array(),
	),
	array(
		array( 1, 2, 3, 4 ),
		array(
			'type'  => 'array',
			'items' => array(
				'type' => 'integer',
				'max'  => 3,
			),
		),
		array( 1, 2, 3 ),
	),
	array(
		array( 1, 'a', true ),
		array(
			'type'  => 'array',
			'items' => array(
				array( 'type' => 'integer' ),
				array( 'type' => 'string' ),
				array( 'type' => 'boolean' ),
			),
		),
		array( 1, 'a', true ),
	),
	array(
		array( 1, 'a', true, 'foo' ),
		array(
			'type'            => 'array',
			'items'           => array(
				array( 'type' => 'integer' ),
				array( 'type' => 'string' ),
				array( 'type' => 'boolean' ),
			),
			'additionalItems' => false,
		),
		$wpct_plugin_test_error,
	),
	array(
		array( 'foo' => 'bar' ),
		array(
			'type'       => 'object',
			'properties' => array(
				'foo' => array( 'type' => 'string' ),
			),
		),
		array( 'foo' => 'bar' ),
	),
	array(
		array(
			'foo' => 'bar',
			'a'   => 1,
		),
		array(
			'type'                 => 'object',
			'properties'           => array(
				'foo' => array( 'type' => 'string' ),
			),
			'additionalProperties' => false,
		),
		array( 'foo' => 'bar' ),
	),
);
