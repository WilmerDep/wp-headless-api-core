<?php
/**
 * Generic form schema normalization helpers.
 *
 * @package HeadlessApiCore
 */

namespace HeadlessApiCore\Modules\Forms;

defined( 'ABSPATH' ) || exit;

final class Forms_Schema {
	const SCHEMA_VERSION = 1;

	/** Return supported field types for the first public contract. */
	public static function field_types() {
		return apply_filters(
			'headless_api_core_forms_field_types',
			array( 'text', 'email', 'tel', 'number', 'textarea', 'select', 'radio', 'checkbox', 'date', 'time', 'hidden', 'rating' )
		);
	}

	/** Decode a JSON meta value into an array. */
	public static function decode_json( $value, $fallback = array() ) {
		if ( is_array( $value ) ) {
			return $value;
		}
		if ( ! is_string( $value ) || '' === trim( $value ) ) {
			return $fallback;
		}
		$decoded = json_decode( $value, true );
		return is_array( $decoded ) ? $decoded : $fallback;
	}

	/** Normalize form sections. */
	public static function normalize_sections( $value ) {
		$value    = self::decode_json( $value );
		$sections = array();
		$seen     = array();

		foreach ( $value as $index => $section ) {
			if ( ! is_array( $section ) ) {
				continue;
			}
			$id = isset( $section['id'] ) ? sanitize_key( $section['id'] ) : '';
			if ( '' === $id || isset( $seen[ $id ] ) ) {
				continue;
			}
			$seen[ $id ] = true;
			$sections[]   = array(
				'id'          => $id,
				'eyebrow'     => isset( $section['eyebrow'] ) ? sanitize_text_field( $section['eyebrow'] ) : '',
				'title'       => isset( $section['title'] ) ? sanitize_text_field( $section['title'] ) : '',
				'description' => isset( $section['description'] ) ? sanitize_textarea_field( $section['description'] ) : '',
				'order'       => isset( $section['order'] ) ? intval( $section['order'] ) : intval( $index ),
			);
		}

		usort( $sections, static function ( $a, $b ) {
			return $a['order'] <=> $b['order'];
		} );
		return $sections;
	}

	/** Normalize one field option. */
	private static function normalize_option( $option ) {
		if ( ! is_array( $option ) ) {
			return null;
		}
		$value = isset( $option['value'] ) ? sanitize_text_field( $option['value'] ) : '';
		$label = isset( $option['label'] ) ? sanitize_text_field( $option['label'] ) : '';
		if ( '' === $value || '' === $label ) {
			return null;
		}
		return array( 'value' => $value, 'label' => $label );
	}

	/** Normalize validation rules without allowing executable expressions. */
	private static function normalize_validation( $validation ) {
		$validation = is_array( $validation ) ? $validation : array();
		$output     = array();
		$int_keys   = array( 'minLength', 'maxLength', 'minWords', 'maxWords' );
		$num_keys   = array( 'min', 'max' );

		foreach ( $int_keys as $key ) {
			if ( isset( $validation[ $key ] ) ) {
				$output[ $key ] = max( 0, absint( $validation[ $key ] ) );
			}
		foreach ( $num_keys as $key ) {
			if ( isset( $validation[ $key ] ) && is_numeric( $validation[ $key ] ) ) {
				$output[ $key ] = (float) $validation[ $key ];
			}
		foreach ( array( 'safeText', 'futureOnly', 'pastOnly' ) as $key ) {
			if ( isset( $validation[ $key ] ) ) {
				$output[ $key ] = (bool) $validation[ $key ];
			}
		if ( isset( $validation['pattern'] ) ) {
			$pattern = sanitize_key( $validation['pattern'] );
			if ( in_array( $pattern, array( 'name', 'digits', 'document' ), true ) ) {
				$output['pattern'] = $pattern;
			}
		}
		return $output;
	}

	/** Normalize field visibility rules. */
	private static function normalize_visibility( $visibility ) {
		if ( ! is_array( $visibility ) ) {
			return null;
		}
		$mode = isset( $visibility['mode'] ) && 'hide' === $visibility['mode'] ? 'hide' : 'show';
		$all  = isset( $visibility['all'] ) && is_array( $visibility['all'] ) ? $visibility['all'] : array();
		$out  = array();
		$operators = array( 'equals', 'not_equals', 'in', 'not_in', 'not_empty', 'empty' );

		foreach ( $all as $rule ) {
			if ( ! is_array( $rule ) ) {
				continue;
			}
			$field    = isset( $rule['field'] ) ? sanitize_key( $rule['field'] ) : '';
			$operator = isset( $rule['operator'] ) ? sanitize_key( $rule['operator'] ) : '';
			if ( '' === $field || ! in_array( $operator, $operators, true ) ) {
				continue;
			}
			$value = isset( $rule['value'] ) ? $rule['value'] : '';
			if ( is_array( $value ) ) {
				$value = array_values( array_filter( array_map( 'sanitize_text_field', $value ), 'strlen' ) );
			} else {
				$value = sanitize_text_field( $value );
			}
			$out[] = array( 'field' => $field, 'operator' => $operator, 'value' => $value );
		}
		return empty( $out ) ? null : array( 'mode' => $mode, 'all' => $out );
	}

	/** Normalize public form fields. */
	public static function normalize_fields( $value ) {
		$value  = self::decode_json( $value );
		$fields = array();
		$seen   = array();
		$types  = self::field_types();

		foreach ( $value as $index => $field ) {
			if ( ! is_array( $field ) ) {
				continue;
			}
			$name = isset( $field['name'] ) ? sanitize_key( $field['name'] ) : '';
			if ( '' === $name || isset( $seen[ $name ] ) ) {
				continue;
			}
			$type = isset( $field['type'] ) ? sanitize_key( $field['type'] ) : 'text';
			if ( ! in_array( $type, $types, true ) ) {
				$type = 'text';
			}
			$seen[ $name ] = true;
			$options       = array();
			if ( isset( $field['options'] ) && is_array( $field['options'] ) ) {
				foreach ( $field['options'] as $option ) {
					$normalized = self::normalize_option( $option );
					if ( $normalized ) {
						$options[] = $normalized;
					}
				}
			}

			$fields[] = array(
				'id'           => isset( $field['id'] ) ? sanitize_key( $field['id'] ) : $name,
				'name'         => $name,
				'type'         => $type,
				'label'        => isset( $field['label'] ) ? sanitize_text_field( $field['label'] ) : $name,
				'placeholder'  => isset( $field['placeholder'] ) ? sanitize_text_field( $field['placeholder'] ) : '',
				'required'     => ! empty( $field['required'] ),
				'section'      => isset( $field['section'] ) ? sanitize_key( $field['section'] ) : '',
				'order'        => isset( $field['order'] ) ? intval( $field['order'] ) : intval( $index ),
				'width'        => isset( $field['width'] ) ? max( 1, min( 12, absint( $field['width'] ) ) ) : 12,
				'hidden'       => ! empty( $field['hidden'] ),
				'helper'       => isset( $field['helper'] ) ? sanitize_text_field( $field['helper'] ) : '',
				'defaultValue' => isset( $field['defaultValue'] ) ? sanitize_text_field( $field['defaultValue'] ) : '',
				'autocomplete' => isset( $field['autocomplete'] ) ? sanitize_text_field( $field['autocomplete'] ) : '',
				'options'      => $options,
				'validation'   => self::normalize_validation( isset( $field['validation'] ) ? $field['validation'] : array() ),
				'visibility'   => self::normalize_visibility( isset( $field['visibility'] ) ? $field['visibility'] : null ),
				'ui'           => isset( $field['ui'] ) && is_array( $field['ui'] ) ? array_map( 'sanitize_text_field', $field['ui'] ) : array(),
			);
		}

		usort( $fields, static function ( $a, $b ) {
			return $a['order'] <=> $b['order'];
		} );
		return $fields;
	}

	/** Sanitize static email addresses or {{field.name}} recipient tokens. */
	private static function normalize_recipient( $value ) {
		$value = trim( (string) $value );
		if ( is_email( $value ) ) {
			return $value;
		}
		if ( preg_match( '/^\{\{field\.([A-Za-z0-9_-]+)\}\}$/', $value, $matches ) ) {
			return '{{field.' . sanitize_key( $matches[1] ) . '}}';
		}
		return '';
	}

	/** Normalize form notifications. */
	public static function normalize_notifications( $value ) {
		$value = self::decode_json( $value );
		$out   = array();
		foreach ( $value as $index => $notification ) {
			if ( ! is_array( $notification ) ) {
				continue;
			}
			$recipients = array();
			foreach ( array( 'to', 'cc', 'bcc' ) as $key ) {
				$list = isset( $notification[ $key ] ) && is_array( $notification[ $key ] ) ? $notification[ $key ] : array();
				$recipients[ $key ] = array_values( array_filter( array_map( array( __CLASS__, 'normalize_recipient' ), $list ) ) );
			}
			$out[] = array(
				'id'           => isset( $notification['id'] ) ? sanitize_key( $notification['id'] ) : 'notification-' . intval( $index + 1 ),
				'enabled'      => ! isset( $notification['enabled'] ) || (bool) $notification['enabled'],
				'template'     => isset( $notification['template'] ) ? sanitize_title( $notification['template'] ) : '',
				'to'           => $recipients['to'],
				'cc'           => $recipients['cc'],
				'bcc'          => $recipients['bcc'],
				'replyToField' => isset( $notification['replyToField'] ) ? sanitize_key( $notification['replyToField'] ) : '',
				'subject'      => isset( $notification['subject'] ) ? sanitize_text_field( $notification['subject'] ) : '',
			);
		}
		return $out;
	}

	/** Normalize anti-spam policy. */
	public static function normalize_anti_spam( $value ) {
		$value = self::decode_json( $value );
		return array(
			'honeypot'      => ! isset( $value['honeypot'] ) || (bool) $value['honeypot'],
			'honeypotField' => isset( $value['honeypotField'] ) ? sanitize_key( $value['honeypotField'] ) : 'website',
			'maxPayload'    => isset( $value['maxPayload'] ) ? max( 4096, min( 262144, absint( $value['maxPayload'] ) ) ) : 65536,
		);
	}

	/** Normalize rate-limit policy. */
	public static function normalize_rate_limit( $value ) {
		$value = self::decode_json( $value );
		return array(
			'enabled' => ! isset( $value['enabled'] ) || (bool) $value['enabled'],
			'max'     => isset( $value['max'] ) ? max( 1, min( 100, absint( $value['max'] ) ) ) : 5,
			'window'  => isset( $value['window'] ) ? max( 60, min( DAY_IN_SECONDS, absint( $value['window'] ) ) ) : 900,
		);
	}
}
