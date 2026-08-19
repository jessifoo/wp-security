<?php
/**
 * Mock wpdb class for testing
 */
class wpdb {
	public $prefix         = 'wp_';
	public $last_error     = '';
	public $last_query     = '';
	public $results        = array();
	public $prepared_query = '';
	public $results_queue  = array();

	public function __construct( $user, $password, $name, $host ) {}

	public function prepare( $query, ...$args ) {
		$placeholders         = array( '%s', '%d', '%f', '%i' );
		$index                = 0;
		$result               = preg_replace_callback(
			'/%[sdfi]/',
			function ( $match ) use ( $args, &$index ) {
				$val = isset( $args[ $index ] ) ? $args[ $index ] : '';
				++$index;
				if ( '%d' === $match[0] ) {
					return (int) $val;
				} elseif ( '%f' === $match[0] ) {
					return (float) $val;
				} elseif ( '%i' === $match[0] ) {
					return '`' . str_replace( '`', '``', $val ) . '`';
				}
				return "'" . addslashes( (string) $val ) . "'";
			},
			$query
		);
		$this->prepared_query = $result;
		return $this->prepared_query;
	}

	public function get_results( $query, $output = OBJECT ) {
		$this->last_query = $query;
		if ( ! empty( $this->results_queue ) ) {
			return array_shift( $this->results_queue );
		}
		return $this->results;
	}

	public function get_var( $query, $x = 0, $y = 0 ) {
		$this->last_query = $query;
		if ( ! empty( $this->results_queue ) ) {
			$result = array_shift( $this->results_queue );
			return is_array( $result ) ? ( isset( $result[0] ) ? $result[0] : null ) : $result;
		}
		return isset( $this->results[0] ) ? $this->results[0] : null;
	}

	public function get_col( $query, $x = 0 ) {
		$this->last_query = $query;
		if ( ! empty( $this->results_queue ) ) {
			return array_shift( $this->results_queue );
		}
		return $this->results;
	}

	public function query( $query ) {
		$this->last_query = $query;
		return true;
	}
}
