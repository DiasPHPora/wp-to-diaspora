<?php
/**
 * General test helper functions.
 *
 * @package WP_To_Diaspora\Tests\Helpers
 * @since   1.6.0
 */

/**
 * Call a private method.
 *
 * If any parameters should be passed, they can just be added after the $method parameter.
 * Found at http://stackoverflow.com/a/18020652/3757422
 *
 * @since 1.6.0
 *
 * @param object $object Object to call the method on.
 * @param string $method The private method to be called.
 *
 * @return mixed The return value of the called private method.
 */
function wp2d_helper_call_private_method( $object, $method ) {
	$refClass  = new ReflectionClass( $object );
	$refMethod = $refClass->getMethod( $method );
	$refMethod->setAccessible( true );

	$params = array_slice( func_get_args(), 2 ); // Get all the parameters after $method.

	return $refMethod->invokeArgs( $object, $params );
}

/**
 * Set the value of a private property of an object.
 *
 * @since 1.6.0
 *
 * @param object $object   Object that contains the private property.
 * @param string $property Name of the property who's value we want to set.
 * @param mixed  $value    The value to set to the property.
 */
function wp2d_helper_set_private_property( $object, $property, $value ) {
	$refObject   = new ReflectionObject( $object );
	$refProperty = $refObject->getProperty( $property );
	$refProperty->setAccessible( true );
	$refProperty->setValue( $object, $value );
}

/**
 * Get the value of a private property of an object.
 *
 * @since unreleased
 *
 * @param object $object   Object that contains the private property.
 * @param string $property Name of the property who's value we want to set.
 *
 * @return mixed
 */
function wp2d_helper_get_private_property( $object, $property ): mixed {
	$refObject   = new ReflectionObject( $object );
	$refProperty = $refObject->getProperty( $property );
	$refProperty->setAccessible( true );
	return $refProperty->getValue( $object );
}
