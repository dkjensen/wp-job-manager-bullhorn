<?php
/**
 * Class AdapterTest
 *
 * @package Wp_Job_Manager_Jobadder_Api
 */


namespace SeattleWebCo\WPJobManager\Recruiter\Bullhorn;


class AdapterTest extends \WP_UnitTestCase {

	public $provider;


	public function setUp() {
		$this->provider = $this->getMockBuilder( '\SeattleWebCo\WPJobManager\Recruiter\Bullhorn\BullhornProvider' )
						 ->setConstructorArgs( array( array( 'test', 'test', admin_url( 'edit.php?post_type=job_listing&page=job-manager-settings' ) ) ) )
						 ->getMock();
	}


	public function test_connected() {
		$adapter = $this->getMockBuilder( '\SeattleWebCo\WPJobManager\Recruiter\Bullhorn\Bullhorn_Adapter' )
						->setConstructorArgs( array( $this->provider ) )
						->getMock();

		$adapter->expects( $this->any() )
				->method( 'connected' )
				->will( $this->returnValue( true ) );

		$client = new Client( $adapter );

		$this->assertTrue( $client->connected() );
	}
}
