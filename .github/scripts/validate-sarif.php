<?php
/**
 * Validate and sanitize SARIF files to ensure startLine >= 1
 *
 * This script fixes invalid startLine values in SARIF files before uploading to GitHub Security.
 */

if ( $argc < 2 ) {
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI script output.
	echo "Usage: php validate-sarif.php <sarif-file>\n";
	exit( 1 );
}

$sarif_file = $argv[1];

if ( ! file_exists( $sarif_file ) ) {
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI script output, file path is from command line argument.
	echo "Error: SARIF file not found: " . escapeshellarg( $sarif_file ) . "\n";
	exit( 1 );
}

// Read file safely and validate read success.
$raw = file_get_contents( $sarif_file );
if ( false === $raw ) {
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI script output.
	fwrite( STDERR, "Error: Failed to read SARIF file: " . escapeshellarg( $sarif_file ) . "\n" );
	exit( 1 );
}

$json = json_decode( $raw, true );
if ( json_last_error() !== JSON_ERROR_NONE ) {
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI script output, error message is from PHP function.
	echo "Error: Invalid JSON in SARIF file: " . escapeshellarg( json_last_error_msg() ) . "\n";
	exit( 1 );
}

$fixed_count = 0;
$removed_count = 0;

if ( isset( $json['runs'] ) ) {
	foreach ( $json['runs'] as $run_index => &$run ) {
		if ( ! isset( $run['results'] ) ) {
			continue;
		}

		$valid_results = array();

		foreach ( $run['results'] as $result_index => $result ) {
			$is_valid = true;

			if ( isset( $result['locations'] ) ) {
				foreach ( $result['locations'] as $loc_index => &$location ) {
					if ( isset( $location['physicalLocation']['region']['startLine'] ) ) {
						$line = &$location['physicalLocation']['region']['startLine'];

						if ( $line < 1 ) {
							// Try to fix by setting to 1, or remove the location if it's invalid
							if ( isset( $location['physicalLocation']['artifactLocation']['uri'] ) ) {
								// If we have a file URI, set line to 1 as fallback
								$line = 1;
								++$fixed_count;
							} else {
								// Remove invalid location without file reference
								unset( $location['physicalLocation']['region']['startLine'] );
								if ( empty( $location['physicalLocation']['region'] ) ) {
									unset( $location['physicalLocation']['region'] );
								}
								if ( empty( $location['physicalLocation'] ) ) {
									unset( $location['physicalLocation'] );
								}
								if ( empty( $location ) ) {
									unset( $result['locations'][ $loc_index ] );
								}
							}
						}
					}
				}

				// Re-index array after unsetting elements
				$result['locations'] = array_values( $result['locations'] );

				// Remove result if it has no valid locations
				if ( empty( $result['locations'] ) ) {
					++$removed_count;
					$is_valid = false;
				}
			}

			if ( $is_valid ) {
				$valid_results[] = $result;
			}
		}

		$run['results'] = $valid_results;
	}
}

// Write sanitized SARIF back to file
$json_output = json_encode( $json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
if ( false === $json_output ) {
	fwrite( STDERR, "Error: Failed to encode SARIF JSON: " . json_last_error_msg() . "\n" );
	exit( 1 );
}

$expected_bytes = strlen( $json_output );
$bytes_written  = file_put_contents( $sarif_file, $json_output );
if ( false === $bytes_written || $bytes_written < $expected_bytes ) {
	$error_msg = false === $bytes_written
		? "Failed to write SARIF file (permission or disk error)"
		: sprintf( "Incomplete write: expected %d bytes, wrote %d bytes", $expected_bytes, $bytes_written );
	fwrite( STDERR, "Error: {$error_msg}: " . escapeshellarg( $sarif_file ) . "\n" );
	exit( 1 );
}

if ( $fixed_count > 0 || $removed_count > 0 ) {
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI script output, file path is from command line argument.
	echo "Sanitized SARIF file: " . escapeshellarg( $sarif_file ) . "\n";
	if ( $fixed_count > 0 ) {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI script output, count is integer.
		echo "  Fixed " . (int) $fixed_count . " invalid startLine values\n";
	}
	if ( $removed_count > 0 ) {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI script output, count is integer.
		echo "  Removed " . (int) $removed_count . " results with invalid locations\n";
	}
} else {
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI script output, file path is from command line argument.
	echo "SARIF file is valid: " . escapeshellarg( $sarif_file ) . "\n";
}

exit( 0 );
