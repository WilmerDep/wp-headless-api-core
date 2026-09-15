<?php
/**
 * Generic provider-side form validation engine.
 *
 * @package HeadlessApiCore
 */

namespace HeadlessApiCore\Modules\Forms;

defined( 'ABSPATH' ) || exit;

final class Forms_Validator {
	/** Validate and sanitize a submission against normalized fields. */
	public function validate( array $fields, array $payload, $honeypot_field = 'website' ) {
		$allowed = array();
		foreach ( $fields as $field ) {
			if ( isset( $field['name'] ) ) {
				$allowed[ $field['name'] ] = true;
			}
		}
		if ( $honeypot_field ) {
			$allowed[ $honeypot_field ] = true;
		}

		$unknown = array_diff( array_keys( $payload ), array_keys( $allowed ) );
		if ( ! empty( $unknown ) ) {
			return array(
				'valid'       => false,
				'values'      => array(),
				'fieldErrors' => array(),
				'error'       => 'unknown_fields',
			);
		}

		$values = array();
		$errors = array();
		foreach ( $fields as $field ) {
			$name = isset( $field['name'] ) ? Forms_Schema::sanitize_field_name( $field['name'] ) : '';
			if ( '' === $name ) {
				continue;
			}

			if ( ! $this->is_visible( $field, $payload ) ) {
				continue;
			}

			$raw       = array_key_exists( $name, $payload ) ? $payload[ $name ] : '';
			$sanitized = $this->sanitize_value( $field, $raw );
			$error     = $this->validate_value( $field, $sanitized );

			if ( '' !== $error ) {
				$errors[ $name ] = $error;
				continue;
			}
			$values[ $name ] = $sanitized;
		}

		return array(
			'valid'       => empty( $errors ),
			'values'      => $values,
			'fieldErrors' => $errors,
			'error'       => empty( $errors ) ? '' : 'validation_failed',
		);
	}

	/** Evaluate normalized field visibility against the raw payload. */
	public function is_visible( array $field, array $payload ) {
		$visibility = isset( $field['visibility'] ) && is_array( $field['visibility'] ) ? $field['visibility'] : null;
		if ( ! $visibility || empty( $visibility['all'] ) ) {
			return empty( $field['hidden'] );
		}

		$matched = true;
		foreach ( $visibility['all'] as $rule ) {
			$current  = isset( $rule['field'] ) && array_key_exists( $rule['field'], $payload ) ? $payload[ $rule['field'] ] : '';
			$expected = isset( $rule['value'] ) ? $rule['value'] : '';
			$operator = isset( $rule['operator'] ) ? $rule['operator'] : 'equals';
			$ok       = $this->rule_matches( $current, $operator, $expected );
			if ( ! $ok ) {
				$matched = false;
				break;
			}
		}

		return isset( $visibility['mode'] ) && 'hide' === $visibility['mode'] ? ! $matched : $matched;
	}

	private function rule_matches( $current, $operator, $expected ) {
		$current_scalar = is_array( $current ) ? $current : trim( (string) $current );
		switch ( $operator ) {
			case 'not_equals':
				return (string) $current_scalar !== (string) $expected;
			case 'in':
				return in_array( (string) $current_scalar, is_array( $expected ) ? array_map( 'strval', $expected ) : array( (string) $expected ), true );
			case 'not_in':
				return ! in_array( (string) $current_scalar, is_array( $expected ) ? array_map( 'strval', $expected ) : array( (string) $expected ), true );
			case 'not_empty':
				return is_array( $current_scalar ) ? ! empty( $current_scalar ) : '' !== (string) $current_scalar;
			case 'empty':
				return is_array( $current_scalar ) ? empty( $current_scalar ) : '' === (string) $current_scalar;
			case 'equals':
			default:
				return (string) $current_scalar === (string) $expected;
		}
	}

	private function sanitize_value( array $field, $value ) {
		$type = isset( $field['type'] ) ? $field['type'] : 'text';

		if ( 'checkbox' === $type && is_array( $value ) ) {
			return array_values( array_map( 'sanitize_text_field', $value ) );
		}
		if ( is_array( $value ) || is_object( $value ) ) {
			return '';
		}

		$value = (string) $value;
		switch ( $type ) {
			case 'email':
				return sanitize_email( $value );
			case 'textarea':
				return sanitize_textarea_field( $value );
			case 'number':
			case 'rating':
				return is_numeric( $value ) ? (float) $value : '';
			case 'checkbox':
				return in_array( strtolower( trim( $value ) ), array( '1', 'true', 'yes', 'on' ), true );
			default:
				return sanitize_text_field( $value );
		}
	}

	private function validate_value( array $field, $value ) {
		$type       = isset( $field['type'] ) ? $field['type'] : 'text';
		$required   = ! empty( $field['required'] );
		$validation = isset( $field['validation'] ) && is_array( $field['validation'] ) ? $field['validation'] : array();
		$empty      = is_array( $value ) ? empty( $value ) : ( '' === $value || null === $value || false === $value );

		if ( $required && $empty ) {
			return __( 'This field is required.', 'wp-headless-api-core' );
		}
		if ( $empty ) {
			return '';
		}

		if ( 'email' === $type && ! is_email( (string) $value ) ) {
			return __( 'Enter a valid email address.', 'wp-headless-api-core' );
		}

		if ( in_array( $type, array( 'select', 'radio', 'checkbox' ), true ) && ! empty( $field['options'] ) ) {
			$allowed = array_map( static function ( $option ) {
				return isset( $option['value'] ) ? (string) $option['value'] : '';
			}, $field['options'] );
			$submitted = is_array( $value ) ? array_map( 'strval', $value ) : array( (string) $value );
			foreach ( $submitted as $item ) {
				if ( ! in_array( $item, $allowed, true ) ) {
					return __( 'Select a valid option.', 'wp-headless-api-core' );
				}
			}
		}

		if ( in_array( $type, array( 'number', 'rating' ), true ) ) {
			if ( ! is_numeric( $value ) ) {
				return __( 'Enter a valid number.', 'wp-headless-api-core' );
			}
			if ( isset( $validation['min'] ) && (float) $value < (float) $validation['min'] ) {
				return __( 'The value is below the allowed minimum.', 'wp-headless-api-core' );
			}
			if ( isset( $validation['max'] ) && (float) $value > (float) $validation['max'] ) {
				return __( 'The value is above the allowed maximum.', 'wp-headless-api-core' );
			}
		}

		$string = is_array( $value ) ? implode( ' ', array_map( 'strval', $value ) ) : (string) $value;
		$length = function_exists( 'mb_strlen' ) ? mb_strlen( $string ) : strlen( $string );
		if ( isset( $validation['minLength'] ) && $length < (int) $validation['minLength'] ) {
			return __( 'The value is too short.', 'wp-headless-api-core' );
		}
		if ( isset( $validation['maxLength'] ) && $length > (int) $validation['maxLength'] ) {
			return __( 'The value is too long.', 'wp-headless-api-core' );
		}

		$words = '' === trim( $string ) ? 0 : count( preg_split( '/\s+/u', trim( $string ) ) );
		if ( isset( $validation['minWords'] ) && $words < (int) $validation['minWords'] ) {
			return __( 'The text does not contain enough words.', 'wp-headless-api-core' );
		}
		if ( isset( $validation['maxWords'] ) && $words > (int) $validation['maxWords'] ) {
			return __( 'The text contains too many words.', 'wp-headless-api-core' );
		}

		if ( ! empty( $validation['safeText'] ) && $this->has_unsafe_text( $string ) ) {
			return __( 'This field contains content that is not allowed.', 'wp-headless-api-core' );
		}

		if ( isset( $validation['pattern'] ) ) {
			$pattern = $validation['pattern'];
			if ( 'name' === $pattern && ! preg_match( "/^[\p{L}\p{M}.'’\-\s]+$/u", $string ) ) {
				return __( 'Use only letters, spaces, apostrophes or hyphens.', 'wp-headless-api-core' );
			}
			if ( 'digits' === $pattern && ! preg_match( '/^\d+$/', preg_replace( '/\s+/', '', $string ) ) ) {
				return __( 'Use digits only.', 'wp-headless-api-core' );
			}
			if ( 'document' === $pattern && ! preg_match( '/^[A-Za-z0-9-]+$/', $string ) ) {
				return __( 'Enter a valid document value.', 'wp-headless-api-core' );
			}
		}

		if ( 'date' === $type ) {
			$date = \DateTimeImmutable::createFromFormat( '!Y-m-d', $string );
			if ( ! $date || $date->format( 'Y-m-d' ) !== $string ) {
				return __( 'Enter a valid date.', 'wp-headless-api-core' );
			}
			$today = new \DateTimeImmutable( 'today', wp_timezone() );
			if ( ! empty( $validation['futureOnly'] ) && $date < $today ) {
				return __( 'The date cannot be in the past.', 'wp-headless-api-core' );
			}
			if ( ! empty( $validation['pastOnly'] ) && $date > $today ) {
				return __( 'The date cannot be in the future.', 'wp-headless-api-core' );
			}
		}

		return '';
	}

	private function has_unsafe_text( $value ) {
		$url_pattern    = '/(?:https?:\/\/|www\.|(?:^|\s)[a-z0-9][a-z0-9.-]+\.(?:com|net|org|gov|edu|io|co|do|me|app|dev)(?:\/|\s|$))/i';
		$markup_pattern = '/[<>`{}\[\]]|[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/';
		$sql_pattern    = '/(?:\bunion\s+select\b|\bdrop\s+table\b|\binsert\s+into\b|\bdelete\s+from\b|\bupdate\s+\w+\s+set\b|\bor\s+1\s*=\s*1\b|\/\*|\*\/|<\/?script\b)/i';
		return preg_match( $url_pattern, $value ) || preg_match( $markup_pattern, $value ) || preg_match( $sql_pattern, $value );
	}
}
