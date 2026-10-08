<?php
/**
 * Tests for the email log: row building, query building and the wp_mail() status flow.
 *
 * @package Camaleaunmail
 */

declare( strict_types=1 );

namespace Camaleaunmail\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Camaleaunmail\Logs;
use Camaleaunmail\MailLogger;
use PHPUnit\Framework\TestCase;

/**
 * In-memory stand-in for $wpdb that records inserts and updates.
 */
class FakeWpdb {
	/** @var string */
	public $prefix = 'wp_';
	/** @var int */
	public $insert_id = 0;
	/** @var array<int,array<string,mixed>> */
	public $rows = array();

	public function insert( string $table, array $data ) {
		$this->insert_id = count( $this->rows ) + 1;
		$this->rows[ $this->insert_id ] = $data;
		return 1;
	}

	public function update( string $table, array $data, array $where ) {
		$this->rows[ $where['id'] ] = array_merge( $this->rows[ $where['id'] ], $data );
		return 1;
	}

	public function esc_like( string $text ): string {
		return addcslashes( $text, '_%\\' );
	}
}

class MailLoggerTest extends TestCase {

	/** @var FakeWpdb */
	private $wpdb;

	/** @var array<string,mixed> */
	private $settings = array();

	/** @var array<string,mixed> */
	private $plugin_settings = array();

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		$this->wpdb      = new FakeWpdb();
		$GLOBALS['wpdb'] = $this->wpdb;

		$this->settings        = array( 'transport' => 'smtp' );
		$this->plugin_settings = array( 'logging_enabled' => true );

		Functions\when( 'get_option' )->alias(
			function ( $name, $default = false ) {
				if ( 'camaleaunmail_settings' === $name ) {
					return $this->settings;
				}
				if ( 'camaleaunmail_plugin_settings' === $name ) {
					return $this->plugin_settings;
				}
				return $default;
			}
		);
	}

	protected function tearDown(): void {
		// Reset the logger's stack of in-progress rows between tests.
		$ref = new \ReflectionProperty( MailLogger::class, 'stack' );
		$ref->setAccessible( true );
		$ref->setValue( null, array() );
		unset( $GLOBALS['wpdb'] );
		Monkey\tearDown();
		parent::tearDown();
	}

	private function atts(): array {
		return array(
			'to'          => array( 'a@example.com', 'b@example.com' ),
			'subject'     => 'Hello',
			'message'     => '<p>Hi</p>',
			'headers'     => "From: Site <site@example.com>\r\nCc: c@example.com\r\nBcc: d@example.com\r\nContent-Type: text/html; charset=UTF-8",
			'attachments' => array( '/tmp/report.pdf', 'Custom name.csv' => '/tmp/x.csv' ),
		);
	}

	public function test_row_from_atts_parses_recipients_headers_and_attachments(): void {
		$row = MailLogger::row_from_atts( $this->atts() );

		$this->assertSame( 'a@example.com, b@example.com', $row['to_email'] );
		$this->assertSame( 'c@example.com', $row['cc'] );
		$this->assertSame( 'd@example.com', $row['bcc'] );
		$this->assertSame( 'site@example.com', $row['from_email'] );
		$this->assertSame( 'Site', $row['from_name'] );
		$this->assertSame( 'text/html', $row['content_type'] );
		$this->assertSame( '["report.pdf","Custom name.csv"]', $row['attachments'] );
		$this->assertStringNotContainsString( "\r", $row['headers'] );
	}

	public function test_sent_flow_records_pending_then_sent(): void {
		$result = MailLogger::pre_wp_mail( null, $this->atts() );
		$this->assertNull( $result );
		$this->assertSame( 'pending', $this->wpdb->rows[1]['status'] );

		MailLogger::succeeded();
		$this->assertSame( 'sent', $this->wpdb->rows[1]['status'] );
	}

	public function test_failed_flow_records_error(): void {
		MailLogger::pre_wp_mail( null, $this->atts() );
		MailLogger::failed( new \WP_Error( 'wp_mail_failed', 'SMTP connect() failed.' ) );

		$this->assertSame( 'failed', $this->wpdb->rows[1]['status'] );
		$this->assertSame( 'SMTP connect() failed.', $this->wpdb->rows[1]['error'] );
	}

	public function test_sending_disabled_blocks_and_logs(): void {
		$this->settings['sending_disabled'] = true;

		$result = MailLogger::pre_wp_mail( null, $this->atts() );

		$this->assertTrue( $result );
		$this->assertSame( 'blocked', $this->wpdb->rows[1]['status'] );
		$this->assertSame( 'site@example.com', $this->wpdb->rows[1]['from_email'] );

		// A later success hook (from another email) must not touch the blocked row.
		MailLogger::succeeded();
		$this->assertSame( 'blocked', $this->wpdb->rows[1]['status'] );
	}

	public function test_sending_disabled_without_logging_still_blocks(): void {
		$this->settings['sending_disabled']       = true;
		$this->plugin_settings['logging_enabled'] = false;

		$this->assertTrue( MailLogger::pre_wp_mail( null, $this->atts() ) );
		$this->assertSame( array(), $this->wpdb->rows );
	}

	public function test_short_circuit_from_another_plugin_is_kept(): void {
		$result = MailLogger::pre_wp_mail( false, $this->atts() );

		$this->assertFalse( $result );
		$this->assertSame( 'short_circuited', $this->wpdb->rows[1]['status'] );
	}

	public function test_nested_wp_mail_updates_the_right_rows(): void {
		MailLogger::pre_wp_mail( null, $this->atts() );
		MailLogger::pre_wp_mail( null, $this->atts() );

		MailLogger::succeeded();
		$this->assertSame( 'sent', $this->wpdb->rows[2]['status'] );
		$this->assertSame( 'pending', $this->wpdb->rows[1]['status'] );

		MailLogger::failed( new \WP_Error( 'wp_mail_failed', 'nope' ) );
		$this->assertSame( 'failed', $this->wpdb->rows[1]['status'] );
	}

	public function test_build_query_filters_and_paginates(): void {
		list( $where, $params, $page, $per_page ) = Logs::build_query(
			array(
				'page'     => 3,
				'per_page' => 500,
				'status'   => 'failed',
				'search'   => '50%_off',
			)
		);

		$this->assertSame( 'WHERE status = %s AND (to_email LIKE %s OR subject LIKE %s)', $where );
		$this->assertSame( array( 'failed', '%50\\%\\_off%', '%50\\%\\_off%' ), $params );
		$this->assertSame( 3, $page );
		$this->assertSame( 100, $per_page );
	}

	public function test_build_query_ignores_unknown_status(): void {
		list( $where, $params ) = Logs::build_query( array( 'status' => 'nope' ) );

		$this->assertSame( '', $where );
		$this->assertSame( array(), $params );
	}
}
