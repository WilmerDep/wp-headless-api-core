<?php
/**
 * Generic public form submission pipeline.
 *
 * @package HeadlessApiCore
 */

namespace HeadlessApiCore\Modules\Forms;

use HeadlessApiCore\Modules\Mail\Mail_Settings;
use WP_Error;
use WP_Post;

defined( 'ABSPATH' ) || exit;

final class Forms_Submission {
	/** @var Mail_Settings */
	private $mail_settings;

	/** @var Forms_Validator */
	private $validator;

	/** @var Mail_Template_Renderer */
	private $renderer;

	public function __construct( Mail_Settings $mail_settings, Forms_Validator $validator, Mail_Template_Renderer $renderer ) {
		$this->mail_settings = $mail_settings;
		$this->validator     = $validator;
		$this->renderer      = $renderer;
	}

	/** Process one anonymous submission without exposing transport internals. */
	public function submit( WP_Post $post, array $payload, $client_ip = '' ) {
		if ( 'publish' !== $post->post_status || ! (bool) get_post_meta( $post->ID, Forms_Post_Type::META_ENABLED, true ) ) {
			return new WP_Error( 'form_unavailable', __( 'This form is not available.', 'wp-headless-api-core' ), array( 'status' => 404 ) );
		}

		$fields        = Forms_Schema::normalize_fields( get_post_meta( $post->ID, Forms_Post_Type::META_FIELDS, true ) );
		$notifications = Forms_Schema::normalize_notifications( get_post_meta( $post->ID, Forms_Post_Type::META_NOTIFICATIONS, true ) );
		$anti_spam     = Forms_Schema::normalize_anti_spam( get_post_meta( $post->ID, Forms_Post_Type::META_ANTI_SPAM, true ) );
		$rate_limit    = Forms_Schema::normalize_rate_limit( get_post_meta( $post->ID, Forms_Post_Type::META_RATE_LIMIT, true ) );

		$encoded = wp_json_encode( $payload );
		if ( false === $encoded || strlen( $encoded ) > (int) $anti_spam['maxPayload'] ) {
			return new WP_Error( 'payload_too_large', __( 'The submitted form is too large.', 'wp-headless-api-core' ), array( 'status' => 413 ) );
		}

		$honeypot = isset( $anti_spam['honeypotField'] ) ? Forms_Schema::sanitize_field_name( $anti_spam['honeypotField'] ) : 'website';
		if ( ! empty( $anti_spam['honeypot'] ) && isset( $payload[ $honeypot ] ) && '' !== trim( (string) $payload[ $honeypot ] ) ) {
			return array( 'ok' => true, 'discarded' => true );
		}

		if ( ! empty( $rate_limit['enabled'] ) && ! $this->consume_rate_limit( $post->ID, $client_ip, $rate_limit ) ) {
			return new WP_Error( 'rate_limited', __( 'Too many submissions. Please try again later.', 'wp-headless-api-core' ), array( 'status' => 429 ) );
		}

		$validation = $this->validator->validate( $fields, $payload, ! empty( $anti_spam['honeypot'] ) ? $honeypot : '' );
		if ( empty( $validation['valid'] ) ) {
			return new WP_Error(
				'form_validation_failed',
				__( 'Review the highlighted fields before submitting.', 'wp-headless-api-core' ),
				array(
					'status'      => 400,
					'fieldErrors' => isset( $validation['fieldErrors'] ) ? $validation['fieldErrors'] : array(),
				)
			);
		}

		$values = $validation['values'];
		$active_notifications = array_values( array_filter( $notifications, static function ( $item ) {
			return ! empty( $item['enabled'] );
		} ) );

		if ( ! empty( $active_notifications ) && ! $this->mail_settings->is_ready() ) {
			return new WP_Error( 'mail_unavailable', __( 'The delivery service is temporarily unavailable.', 'wp-headless-api-core' ), array( 'status' => 503 ) );
		}

		$submission_id   = function_exists( 'wp_generate_uuid4' ) ? wp_generate_uuid4() : uniqid( 'form-', true );
		$submission_date = current_time( 'c' );
		$form_context    = array(
			'id'             => (int) $post->ID,
			'slug'           => $post->post_name,
			'title'          => get_the_title( $post ),
			'submissionId'   => $submission_id,
			'submissionDate' => $submission_date,
		);

		foreach ( $active_notifications as $notification ) {
			$result = $this->send_notification( $notification, $form_context, $values, $fields );
			if ( is_wp_error( $result ) ) {
				return new WP_Error( 'delivery_failed', __( 'We could not deliver the form. Please try again later.', 'wp-headless-api-core' ), array( 'status' => 502 ) );
			}
		}

		do_action( 'headless_api_core_form_submitted', $post->ID, $values, $submission_id );

		return array(
			'ok'           => true,
			'submissionId' => $submission_id,
			'message'      => (string) get_post_meta( $post->ID, Forms_Post_Type::META_SUCCESS_MESSAGE, true ),
		);
	}

	/** Send one normalized notification through wp_mail()/Mail Core. */
	private function send_notification( array $notification, array $form, array $values, array $fields ) {
		$to = $this->resolve_recipients( $notification['to'], $values );
		if ( empty( $to ) ) {
			return new WP_Error( 'notification_recipient_missing' );
		}

		$rendered = $this->renderer->render(
			$notification['template'],
			$form,
			$values,
			$fields,
			isset( $notification['subject'] ) ? $notification['subject'] : ''
		);
		if ( is_wp_error( $rendered ) ) {
			return $rendered;
		}

		$headers   = array( 'Content-Type: text/html; charset=UTF-8' );
		$cc        = $this->resolve_recipients( $notification['cc'], $values );
		$bcc       = $this->resolve_recipients( $notification['bcc'], $values );
		$reply_key = isset( $notification['replyToField'] ) ? Forms_Schema::sanitize_field_name( $notification['replyToField'] ) : '';

		foreach ( $cc as $email ) {
			$headers[] = 'Cc: ' . $email;
		}
		foreach ( $bcc as $email ) {
			$headers[] = 'Bcc: ' . $email;
		}
		if ( $reply_key && isset( $values[ $reply_key ] ) && is_email( $values[ $reply_key ] ) ) {
			$headers[] = 'Reply-To: ' . sanitize_email( $values[ $reply_key ] );
		}

		$alt_body = isset( $rendered['text'] ) ? (string) $rendered['text'] : '';
		$alt_hook = static function ( $phpmailer ) use ( $alt_body ) {
			if ( '' !== $alt_body ) {
				$phpmailer->AltBody = $alt_body;
			}
		};
		add_action( 'phpmailer_init', $alt_hook, 99 );
		$sent = wp_mail( $to, $rendered['subject'], $rendered['html'], $headers );
		remove_action( 'phpmailer_init', $alt_hook, 99 );

		return $sent ? true : new WP_Error( 'wp_mail_failed' );
	}

	/** Resolve static recipients and approved {{field.name}} email tokens. */
	private function resolve_recipients( array $recipients, array $values ) {
		$out = array();
		foreach ( $recipients as $recipient ) {
			$recipient = trim( (string) $recipient );
			if ( is_email( $recipient ) ) {
				$out[] = sanitize_email( $recipient );
				continue;
			}
			if ( preg_match( '/^\{\{field\.([A-Za-z][A-Za-z0-9_-]*)\}\}$/', $recipient, $matches ) ) {
				$key = Forms_Schema::sanitize_field_name( $matches[1] );
				if ( $key && isset( $values[ $key ] ) && is_email( $values[ $key ] ) ) {
					$out[] = sanitize_email( $values[ $key ] );
				}
			}
		}
		return array_values( array_unique( array_filter( $out ) ) );
	}

	/** Privacy-conscious transient rate limit keyed by form and direct client IP. */
	private function consume_rate_limit( $form_id, $client_ip, array $policy ) {
		$client_ip = trim( (string) $client_ip );
		if ( '' === $client_ip ) {
			$client_ip = 'unknown';
		}
		$key = 'hacf_form_rate_' . substr( hash_hmac( 'sha256', absint( $form_id ) . '|' . $client_ip, wp_salt( 'nonce' ) ), 0, 32 );
		$state = get_transient( $key );
		$state = is_array( $state ) ? $state : array( 'count' => 0 );
		if ( isset( $state['count'] ) && (int) $state['count'] >= (int) $policy['max'] ) {
			return false;
		}
		$state['count'] = isset( $state['count'] ) ? (int) $state['count'] + 1 : 1;
		set_transient( $key, $state, (int) $policy['window'] );
		return true;
	}
}
