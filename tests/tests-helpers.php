<?php
/**
 * WP2D_Helpers tests.
 *
 * @package WP_To_Diaspora\Tests\WP2D_Helpers
 * @since   1.7.0
 */

/**
 * Main API test class.
 *
 * @since 1.7.0
 */
class Tests_WP2D_Helpers extends WP_UnitTestCase {

	/**
	 * Test trying to add and get debug info when debugging is disabled.
	 *
	 * NOTE: Because of the constant, this test needs to run in
	 * a separate process without preserving the global state.
	 *
	 * @since 1.7.0
	 *
	 * @preserveGlobalState disabled
	 * @runInSeparateProcess
	 */
	public function test_debugging_disabled() {
		$this->assertClassHasAttribute( 'debugging', 'WP2D_Helpers' );
		$this->assertAttributeEmpty( 'debugging', 'WP2D_Helpers' );

		define( 'WP2D_DEBUGGING', false );

		$this->assertFalse( WP2D_Helpers::add_debugging( 'some debug info' ) );
		$this->assertAttributeEmpty( 'debugging', 'WP2D_Helpers' );

		$this->assertFalse( WP2D_Helpers::get_debugging() );
	}

	/**
	 * Test adding and getting debug info.
	 *
	 * NOTE: Because of the constant, this test needs to run in
	 * a separate process without preserving the global state.
	 *
	 * @since 1.7.0
	 *
	 * @preserveGlobalState disabled
	 * @runInSeparateProcess
	 */
	public function test_debugging_enabled() {
		$this->assertClassHasAttribute( 'debugging', 'WP2D_Helpers' );
		$this->assertAttributeEmpty( 'debugging', 'WP2D_Helpers' );

		define( 'WP2D_DEBUGGING', true );

		$this->assertTrue( WP2D_Helpers::add_debugging( 'some debug info' ) );
		$this->assertContains( 'some debug info', WP2D_Helpers::get_debugging() );

		$this->assertTrue( WP2D_Helpers::add_debugging( 'some more debug info' ) );
		$this->assertContains( 'some debug info', WP2D_Helpers::get_debugging() );
		$this->assertContains( 'some more debug info', WP2D_Helpers::get_debugging() );
	}

	/**
	 * Test converting a CSV string to an array.
	 *
	 * @since 1.7.0
	 */
	public function test_str_to_arr() {
		// If we're already an array, stay an array.
		$arr1a = [ 'a', 'b' ];
		$arr1b = WP2D_Helpers::str_to_arr( $arr1a );
		$this->assertEquals( [ 'a', 'b' ], $arr1b );
		$this->assertEquals( $arr1a, $arr1b );

		// If we're a messy array, have it cleaned up.
		$arr2a = [ 'a ', ' b', null, 'c', 'd', ' ', 'e', ' ' ];
		$arr2b = WP2D_Helpers::str_to_arr( $arr2a );
		$this->assertEquals( [ 'a', 'b', 'c', 'd', 'e' ], $arr2b );
		$this->assertEquals( $arr2a, $arr2b );

		// Empty string gets empty array.
		$str3 = '';
		$arr3 = WP2D_Helpers::str_to_arr( $str3 );
		$this->assertEquals( [], $arr3 );
		$this->assertEquals( $str3, $arr3 );

		// Simple string with no commas.
		$str4 = 'a';
		$arr4 = WP2D_Helpers::str_to_arr( $str4 );
		$this->assertEquals( [ 'a' ], $arr4 );
		$this->assertEquals( $str4, $arr4 );

		// Make sure any wackiness gets cleaned up.
		$str5 = 'a,b, c, d  ,, ,,e';
		$arr5 = WP2D_Helpers::str_to_arr( $str5 );
		$this->assertEquals( [ 'a', 'b', 'c', 'd', 'e' ], $arr5 );
		$this->assertEquals( $str5, $arr5 );
	}

	/**
	 * Test converting an array to a CSV string.
	 *
	 * @since 1.7.0
	 */
	public function test_arr_to_str() {
		// If we're already a string, stay a string.
		$str1a = 'a,b';
		$str1b = WP2D_Helpers::arr_to_str( $str1a );
		$this->assertEquals( 'a,b', $str1b );
		$this->assertEquals( $str1a, $str1b );

		// If we're a messy string, have it cleaned up.
		$str2a = 'a,b, c, d  ,, ,,e';
		$str2b = WP2D_Helpers::arr_to_str( $str2a );
		$this->assertEquals( 'a,b,c,d,e', $str2b );
		$this->assertEquals( $str2a, $str2b );

		// Empty array gets empty string.
		$arr3 = [];
		$str3 = WP2D_Helpers::arr_to_str( $arr3 );
		$this->assertEquals( '', $str3 );
		$this->assertEquals( $arr3, $str3 );

		// Array with only 1 item.
		$arr4 = [ 'a' ];
		$str4 = WP2D_Helpers::arr_to_str( $arr4 );
		$this->assertEquals( 'a', $str4 );
		$this->assertEquals( $arr4, $str4 );

		// Array with multiple items. Make sure any wackiness gets cleaned up.
		$arr5 = [ 'a ', ' b', null, 'c', 'd', ' ', 'e', ' ' ];
		$str5 = WP2D_Helpers::arr_to_str( $arr5 );
		$this->assertEquals( 'a,b,c,d,e', $str5 );
		$this->assertEquals( $arr5, $str5 );
	}

	/**
	 * Test the encryption helpers.
	 *
	 * @since 1.7.0
	 */
	public function test_encryption() {
		// Using the default key (AUTH_KEY).
		$enc1 = WP2D_Helpers::encrypt( 'text-to-encrypt' );
		$this->assertEquals( '6692CEB1300B16CF41E38E6C45BE25DD', $enc1 );
		$dec1 = WP2D_Helpers::decrypt( '6692CEB1300B16CF41E38E6C45BE25DD' );
		$this->assertEquals( 'text-to-encrypt', $dec1 );

		// Using a custom key.
		$enc2 = WP2D_Helpers::encrypt( 'text-to-encrypt', 'custom-key' );
		$this->assertEquals( '51A7CCA3917CC6D5C6FE65A230858F14', $enc2 );
		$dec2 = WP2D_Helpers::decrypt( '51A7CCA3917CC6D5C6FE65A230858F14', 'custom-key' );
		$this->assertEquals( 'text-to-encrypt', $dec2 );

		// We can always encrypt.
		$this->assertNotEmpty( WP2D_Helpers::encrypt( 'i-always-work!' ) );

		// If we actually pass a value, that is...
		$this->assertFalse( WP2D_Helpers::encrypt( '' ) );
		$this->assertFalse( WP2D_Helpers::encrypt( null ) );

		// Same thing applies to decrypting. Where there is nothing, there is nothing.
		$this->assertFalse( WP2D_Helpers::decrypt( '' ) );
		$this->assertFalse( WP2D_Helpers::decrypt( null ) );

		// Failed to decrypt.
		$this->assertNull( WP2D_Helpers::decrypt( 'some-totally-wrong-encrypted-value' ) );
	}

	/**
	 * The API quick connect function.
	 *
	 * @since 1.7.0
	 */
	public function test_api_quick_connect() {
		$this->markTestSkipped( 'Skipping for the moment.' );
	}
}
