<?php
/**
 * Shared symbol-choice challenge (login + public forms).
 *
 * Fully self-hosted – no third-party requests, no image CAPTCHA.
 *
 * @package SentryGate
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SentryGate_Symbol_Challenge
 */
class SentryGate_Symbol_Challenge {

	public const TRANSIENT_PREFIX = 'sentrygate_chal_';

	/**
	 * Create a new challenge token and store the correct choice server-side.
	 */
	public static function create(): string {
		$token   = bin2hex( random_bytes( 16 ) );
		$catalog = self::symbols();
		$keys    = array_keys( $catalog );
		shuffle( $keys );

		$picked      = array_slice( $keys, 0, 4 );
		$correct_key = $picked[ random_int( 0, count( $picked ) - 1 ) ];
		$choices     = array();
		$correct_id  = '';

		foreach ( $picked as $key ) {
			$id        = bin2hex( random_bytes( 8 ) );
			$choices[] = array(
				'key' => $key,
				'id'  => $id,
			);
			if ( $key === $correct_key ) {
				$correct_id = $id;
			}
		}

		shuffle( $choices );

		$data = array(
			'correct_id' => $correct_id,
			'prompt'     => $catalog[ $correct_key ]['label'],
			'choices'    => $choices,
			'created'    => time(),
		);

		set_transient( self::TRANSIENT_PREFIX . $token, $data, 10 * MINUTE_IN_SECONDS );

		return $token;
	}

	/**
	 * Challenge payload for a token, or null if missing/expired.
	 *
	 * @return array<string, mixed>|null
	 */
	public static function get( string $token ): ?array {
		if ( '' === $token ) {
			return null;
		}

		$data = get_transient( self::TRANSIENT_PREFIX . $token );

		return is_array( $data ) ? $data : null;
	}

	/**
	 * Verify a one-time token + choice. Always consumes a found challenge.
	 *
	 * @return bool|WP_Error True on success.
	 */
	public static function verify( string $token, string $choice ): bool|WP_Error {
		$data = self::get( $token );
		if ( null === $data || empty( $data['correct_id'] ) ) {
			return new WP_Error(
				'sentrygate_expired',
				__( 'Sicherheitsprüfung abgelaufen. Bitte neu laden.', 'wiedenroth-technologies-sentrygate' )
			);
		}

		$passed = '' !== $choice && hash_equals( (string) $data['correct_id'], $choice );
		delete_transient( self::TRANSIENT_PREFIX . $token );

		if ( ! $passed ) {
			return new WP_Error(
				'sentrygate_challenge',
				__( 'Bitte das richtige Symbol wählen.', 'wiedenroth-technologies-sentrygate' )
			);
		}

		return true;
	}

	/**
	 * HTML for the symbol grid (no wrapping form).
	 *
	 * @param array<string, mixed> $data Challenge payload.
	 * @param array<string, mixed> $args {
	 *     @type string $uid            Unique prefix for element ids (forms). Empty = login ids.
	 *     @type string $refresh_class  Button class list.
	 * }
	 */
	public static function render_markup( array $data, array $args = array() ): void {
		$prompt  = isset( $data['prompt'] ) ? (string) $data['prompt'] : '';
		$choices = isset( $data['choices'] ) && is_array( $data['choices'] ) ? $data['choices'] : array();
		$catalog = self::symbols();

		$uid           = isset( $args['uid'] ) ? sanitize_html_class( (string) $args['uid'] ) : '';
		$prompt_id     = '' !== $uid ? $uid . '-prompt' : 'sentrygate-choice-prompt';
		$target_id     = '' !== $uid ? $uid . '-target' : 'sentrygate-choice-target';
		$refresh_id    = '' !== $uid ? $uid . '-refresh' : 'sentrygate-refresh';
		$refresh_class = isset( $args['refresh_class'] ) ? (string) $args['refresh_class'] : 'button sentrygate-refresh';

		echo '<fieldset class="sentrygate-choice">';
		echo '<legend class="sentrygate-choice__legend">' . esc_html__( 'Sicherheitsprüfung', 'wiedenroth-technologies-sentrygate' ) . '</legend>';
		echo '<div class="sentrygate-choice__prompt" id="' . esc_attr( $prompt_id ) . '">';
		echo '<span class="sentrygate-choice__prompt-text">' . esc_html__( 'Wählen Sie das Symbol:', 'wiedenroth-technologies-sentrygate' ) . '</span>';
		echo ' <strong class="sentrygate-choice__target" id="' . esc_attr( $target_id ) . '">' . esc_html( $prompt ) . '</strong>';
		echo '</div>';

		echo '<div class="sentrygate-choice__grid" role="radiogroup" aria-labelledby="' . esc_attr( $prompt_id ) . '">';
		foreach ( $choices as $choice ) {
			$key = isset( $choice['key'] ) ? (string) $choice['key'] : '';
			$id  = isset( $choice['id'] ) ? (string) $choice['id'] : '';
			if ( ! isset( $catalog[ $key ] ) || '' === $id ) {
				continue;
			}

			$input_id = 'sentrygate-choice-' . sanitize_html_class( $id );
			$label    = $catalog[ $key ]['label'];
			$svg      = $catalog[ $key ]['svg'];

			echo '<label class="sentrygate-choice__item" for="' . esc_attr( $input_id ) . '" aria-label="' . esc_attr( $label ) . '">';
			printf(
				'<input type="radio" name="sentrygate_choice" id="%1$s" value="%2$s" required />',
				esc_attr( $input_id ),
				esc_attr( $id )
			);
			echo '<span class="sentrygate-choice__icon" aria-hidden="true">' . $svg . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- inline SVG from this class.
			echo '</label>';
		}
		echo '</div>';

		printf(
			'<button type="button" class="%1$s" id="%2$s" data-sentrygate-refresh>%3$s</button>',
			esc_attr( $refresh_class ),
			esc_attr( $refresh_id ),
			esc_html__( 'Andere Symbole', 'wiedenroth-technologies-sentrygate' )
		);
		echo '</fieldset>';
	}

	/**
	 * Markup as string.
	 *
	 * @param array<string, mixed> $data Challenge payload.
	 * @param array<string, mixed> $args Render args.
	 */
	public static function markup( array $data, array $args = array() ): string {
		ob_start();
		self::render_markup( $data, $args );
		return (string) ob_get_clean();
	}

	/**
	 * Symbol catalog.
	 *
	 * @return array<string, array{label: string, svg: string}>
	 */
	public static function symbols(): array {
		return array(
			'lock'   => array(
				'label' => __( 'Schloss', 'wiedenroth-technologies-sentrygate' ),
				'svg'   => self::svg_lock(),
			),
			'key'    => array(
				'label' => __( 'Schlüssel', 'wiedenroth-technologies-sentrygate' ),
				'svg'   => self::svg_key(),
			),
			'mail'   => array(
				'label' => __( 'Brief', 'wiedenroth-technologies-sentrygate' ),
				'svg'   => self::svg_mail(),
			),
			'house'  => array(
				'label' => __( 'Haus', 'wiedenroth-technologies-sentrygate' ),
				'svg'   => self::svg_house(),
			),
			'shield' => array(
				'label' => __( 'Schild', 'wiedenroth-technologies-sentrygate' ),
				'svg'   => self::svg_shield(),
			),
			'globe'  => array(
				'label' => __( 'Globus', 'wiedenroth-technologies-sentrygate' ),
				'svg'   => self::svg_globe(),
			),
		);
	}

	private static function svg( string $inner ): string {
		return '<svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" focusable="false">' . $inner . '</svg>';
	}

	private static function svg_lock(): string {
		return self::svg( '<rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/>' );
	}

	private static function svg_key(): string {
		return self::svg( '<circle cx="8" cy="10" r="3.5"/><path d="M11 10h9v3M17 10v4M20 10v3"/>' );
	}

	private static function svg_mail(): string {
		return self::svg( '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/>' );
	}

	private static function svg_house(): string {
		return self::svg( '<path d="M4 11 12 4l8 7"/><path d="M6 10v9h12v-9"/><path d="M10 19v-5h4v5"/>' );
	}

	private static function svg_shield(): string {
		return self::svg( '<path d="M12 3 5 6v6c0 4.5 3 7.5 7 9 4-1.5 7-4.5 7-9V6z"/>' );
	}

	private static function svg_globe(): string {
		return self::svg( '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c3 3.5 3 14.5 0 18M12 3c-3 3.5-3 14.5 0 18"/>' );
	}
}
