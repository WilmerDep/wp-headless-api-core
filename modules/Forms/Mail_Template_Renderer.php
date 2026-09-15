<?php
/**
 * Reusable form mail-template renderer.
 *
 * @package HeadlessApiCore
 */

namespace HeadlessApiCore\Modules\Forms;

use WP_Error;

defined( 'ABSPATH' ) || exit;

final class Mail_Template_Renderer {
	/** Render one published mail template with escaped form variables. */
	public function render( $template_slug, array $form, array $values, array $fields, $subject_override = '' ) {
		$template_slug = sanitize_title( $template_slug );
		$template      = get_page_by_path( $template_slug, OBJECT, Forms_Post_Type::TEMPLATE_POST_TYPE );
		if ( ! $template || 'publish' !== $template->post_status ) {
			return new WP_Error( 'mail_template_not_found', __( 'Mail template not found.', 'wp-headless-api-core' ) );
		}

		$mode       = (string) get_post_meta( $template->ID, Forms_Post_Type::META_TEMPLATE_MODE, true );
		$subject    = '' !== trim( (string) $subject_override ) ? $subject_override : (string) get_post_meta( $template->ID, Forms_Post_Type::META_TEMPLATE_SUBJECT, true );
		$preheader  = (string) get_post_meta( $template->ID, Forms_Post_Type::META_TEMPLATE_PREHEADER, true );
		$html       = (string) get_post_meta( $template->ID, Forms_Post_Type::META_TEMPLATE_HTML, true );
		$text       = (string) get_post_meta( $template->ID, Forms_Post_Type::META_TEMPLATE_TEXT, true );
		$visual     = Forms_Schema::decode_json( get_post_meta( $template->ID, Forms_Post_Type::META_TEMPLATE_VISUAL, true ), array() );
		$field_rows = $this->visible_field_rows( $fields, $values );
		$context    = $this->context( $form, $values );

		if ( 'html' !== $mode ) {
			$html = $this->render_visual( $visual, $preheader, $field_rows );
		}
		if ( '' === trim( $text ) ) {
			$text = $this->field_rows_text( $field_rows );
		}

		return array(
			'subject' => $this->replace_text_tokens( $subject, $context ),
			'html'    => $this->replace_html_tokens( $html, $context, $field_rows ),
			'text'    => $this->replace_text_tokens( $text, $context, $field_rows ),
		);
	}

	/** Build escaped scalar token context. */
	private function context( array $form, array $values ) {
		$context = array(
			'site.name'       => get_bloginfo( 'name' ),
			'site.url'        => home_url( '/' ),
			'form.name'       => isset( $form['title'] ) ? (string) $form['title'] : '',
			'form.slug'       => isset( $form['slug'] ) ? (string) $form['slug'] : '',
			'submission.id'   => isset( $form['submissionId'] ) ? (string) $form['submissionId'] : '',
			'submission.date' => isset( $form['submissionDate'] ) ? (string) $form['submissionDate'] : gmdate( 'c' ),
		);
		foreach ( $values as $name => $value ) {
			if ( is_scalar( $value ) ) {
				$context[ 'field.' . sanitize_key( $name ) ] = (string) $value;
			} elseif ( is_array( $value ) ) {
				$context[ 'field.' . sanitize_key( $name ) ] = implode( ', ', array_map( 'strval', $value ) );
			}
		}
		return $context;
	}

	/** Return visible field label/value rows in schema order. */
	private function visible_field_rows( array $fields, array $values ) {
		$rows = array();
		foreach ( $fields as $field ) {
			if ( ! is_array( $field ) || ! empty( $field['hidden'] ) ) {
				continue;
			}
			$name = isset( $field['name'] ) ? sanitize_key( $field['name'] ) : '';
			if ( '' === $name || ! array_key_exists( $name, $values ) ) {
				continue;
			}
			$value = $values[ $name ];
			if ( is_array( $value ) ) {
				$value = implode( ', ', array_map( 'strval', $value ) );
			}
			$rows[] = array(
				'label' => isset( $field['label'] ) ? (string) $field['label'] : $name,
				'value' => (string) $value,
			);
		}
		return $rows;
	}

	/** Build the controlled visual-template email HTML. */
	private function render_visual( array $visual, $preheader, array $rows ) {
		$outer     = $this->safe_color( isset( $visual['outerBackground'] ) ? $visual['outerBackground'] : '#f5f8fb', '#f5f8fb' );
		$header    = $this->safe_color( isset( $visual['headerBackground'] ) ? $visual['headerBackground'] : '#06477f', '#06477f' );
		$label     = $this->safe_color( isset( $visual['labelColor'] ) ? $visual['labelColor'] : '#123f68', '#123f68' );
		$value     = $this->safe_color( isset( $visual['valueColor'] ) ? $visual['valueColor'] : '#344f67', '#344f67' );
		$separator = $this->safe_color( isset( $visual['separatorColor'] ) ? $visual['separatorColor'] : '#e8eef3', '#e8eef3' );
		$eyebrow   = isset( $visual['eyebrow'] ) ? sanitize_text_field( $visual['eyebrow'] ) : '{{form.name}}';
		$heading   = isset( $visual['heading'] ) ? sanitize_text_field( $visual['heading'] ) : __( 'New form submission', 'wp-headless-api-core' );
		$intro     = isset( $visual['intro'] ) ? sanitize_textarea_field( $visual['intro'] ) : '';
		$footer    = isset( $visual['footer'] ) ? sanitize_textarea_field( $visual['footer'] ) : '';
		$logo_url  = isset( $visual['logoUrl'] ) ? esc_url_raw( $visual['logoUrl'], array( 'http', 'https' ) ) : '';
		$table     = $this->field_rows_html( $rows, $label, $value, $separator );
		$logo      = '';

		if ( $logo_url ) {
			$logo = '<td style="width:88px;vertical-align:middle;text-align:right;padding:0 0 0 18px"><img src="' . esc_url( $logo_url ) . '" width="72" alt="" style="display:block;max-width:72px;height:auto;margin-left:auto;border:0;outline:none" /></td>';
		}

		$preheader_html = '' !== trim( (string) $preheader )
			? '<div style="display:none!important;max-height:0;overflow:hidden;opacity:0;color:transparent">' . esc_html( $preheader ) . '</div>'
			: '';
		$intro_html  = '' !== $intro ? '<p style="margin:0 0 18px;color:' . esc_attr( $value ) . ';font-size:14px;line-height:1.6">' . nl2br( esc_html( $intro ) ) . '</p>' : '';
		$footer_html = '' !== $footer ? '<div style="padding:0 28px 24px;color:#6f8293;font-size:12px;line-height:1.55">' . nl2br( esc_html( $footer ) ) . '</div>' : '';

		return $preheader_html
			. '<div style="font-family:Arial,Helvetica,sans-serif;background:' . esc_attr( $outer ) . ';padding:24px;color:' . esc_attr( $value ) . '">'
			. '<div style="max-width:720px;margin:0 auto;background:#fff;border:1px solid #e3eaf0;border-radius:18px;overflow:hidden">'
			. '<div style="background:' . esc_attr( $header ) . ';color:#fff;padding:22px 28px"><table role="presentation" style="width:100%;border-collapse:collapse"><tr><td style="vertical-align:middle;padding:0">'
			. '<div style="font-size:12px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;opacity:.82">' . esc_html( $eyebrow ) . '</div>'
			. '<h1 style="margin:8px 0 0;font-size:24px;line-height:1.15;color:#fff">' . esc_html( $heading ) . '</h1></td>' . $logo . '</tr></table></div>'
			. '<div style="padding:24px 28px 28px">' . $intro_html . '{{form.fields}}</div>'
			. $footer_html . '</div></div>';
	}

	/** Replace tokens in HTML while escaping scalar values. */
	private function replace_html_tokens( $html, array $context, array $rows ) {
		$html = str_replace( '{{form.fields}}', $this->field_rows_html( $rows ), (string) $html );
		foreach ( $context as $key => $value ) {
			$html = str_replace( '{{' . $key . '}}', esc_html( $value ), $html );
		}
		return wp_kses_post( $html );
	}

	/** Replace tokens in plain text. */
	private function replace_text_tokens( $text, array $context, array $rows = array() ) {
		$text = str_replace( '{{form.fields}}', $this->field_rows_text( $rows ), (string) $text );
		foreach ( $context as $key => $value ) {
			$text = str_replace( '{{' . $key . '}}', wp_strip_all_tags( $value ), $text );
		}
		return wp_strip_all_tags( $text );
	}

	private function field_rows_html( array $rows, $label_color = '#123f68', $value_color = '#344f67', $separator = '#e8eef3' ) {
		$html = '<table role="presentation" style="width:100%;border-collapse:collapse;font-size:14px">';
		foreach ( $rows as $row ) {
			$html .= '<tr><td style="padding:8px 12px;border-bottom:1px solid ' . esc_attr( $separator ) . ';font-weight:700;color:' . esc_attr( $label_color ) . ';width:180px;vertical-align:top">' . esc_html( $row['label'] ) . '</td><td style="padding:8px 12px;border-bottom:1px solid ' . esc_attr( $separator ) . ';color:' . esc_attr( $value_color ) . ';white-space:pre-wrap">' . nl2br( esc_html( $row['value'] ) ) . '</td></tr>';
		}
		return $html . '</table>';
	}

	private function field_rows_text( array $rows ) {
		$lines = array();
		foreach ( $rows as $row ) {
			$lines[] = $row['label'] . ': ' . $row['value'];
		}
		return implode( "\n", $lines );
	}

	private function safe_color( $value, $fallback ) {
		$value = trim( (string) $value );
		return preg_match( '/^#[0-9a-fA-F]{6}$/', $value ) ? strtolower( $value ) : $fallback;
	}
}
