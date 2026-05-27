<?php
/**
 * Plugin Name: WP Consent API Demo
 * Description: Demonstrates WP Consent API category and service consent in WordPress Playground.
 * Version: 1.0.0
 * Author: WordPress Contributors
 * License: GPL-2.0-or-later
 *
 * @package wordpress/consent-api-demo
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_filter(
	'wp_get_consent_type',
	static function (): string {
		return 'optin';
	}
);

add_filter( 'wp_consent_api_registered_' . plugin_basename( __FILE__ ), '__return_true' );

/**
 * Register demo cookies so the service-level API has data to inspect.
 */
function wp_consent_api_demo_register_cookie_info(): void {
	if ( ! function_exists( 'wp_add_cookie_info' ) ) {
		return;
	}

	wp_add_cookie_info(
		'demo_marketing_id',
		'Demo Analytics',
		'marketing',
		'30 days',
		'Store a demo marketing identifier.',
		'Pseudonymous visitor identifier',
		false,
		false,
		'HTTP'
	);

	wp_add_cookie_info(
		'demo_preference',
		'Demo Preferences',
		'preferences',
		'30 days',
		'Remember the demo preference choice.',
		'',
		false,
		false,
		'LOCALSTORAGE'
	);
}
add_action( 'plugins_loaded', 'wp_consent_api_demo_register_cookie_info', 20 );

/**
 * Enqueue the small interactive demo script.
 */
function wp_consent_api_demo_enqueue_script(): void {
	static $enqueued = false;

	if ( $enqueued ) {
		return;
	}

	wp_enqueue_script( 'wp-consent-api' );

	$enqueued = true;
}

/**
 * Print the interactive demo script.
 */
function wp_consent_api_demo_print_script(): void {
	?>
	<script>
(function () {
	function ready(callback) {
		if (document.readyState !== 'loading') {
			callback();
			return;
		}
		document.addEventListener('DOMContentLoaded', callback);
	}

	ready(function () {
		var root = document.querySelector('[data-wp-consent-api-demo]');
		if (!root) {
			return;
		}

		function updateStatus(name, value) {
			var node = root.querySelector('[data-status="' + name + '"]');
			if (!node) {
				return;
			}

			node.textContent = value ? 'Allowed' : 'Denied';
			node.className = value ? 'is-allowed' : 'is-denied';
		}

		function render() {
			if (typeof wp_has_consent !== 'function') {
				return;
			}

			updateStatus('marketing', wp_has_consent('marketing'));
			updateStatus('preferences', wp_has_consent('preferences'));

			if (typeof wp_has_service_consent === 'function') {
				updateStatus('demo-analytics', wp_has_service_consent('Demo Analytics'));
			}

			if (typeof wp_is_service_denied === 'function') {
				updateStatus('demo-analytics-denied', wp_is_service_denied('Demo Analytics'));
			}

			var cookieOutput = root.querySelector('[data-cookie-output]');
			if (cookieOutput) {
				cookieOutput.textContent = document.cookie || '(none)';
			}
		}

		root.querySelectorAll('[data-category-consent]').forEach(function (button) {
			button.addEventListener('click', function () {
				wp_set_consent(button.dataset.categoryConsent, button.dataset.value);
				render();
			});
		});

		root.querySelectorAll('[data-service-consent]').forEach(function (button) {
			button.addEventListener('click', function () {
				wp_set_service_consent(button.dataset.serviceConsent, button.dataset.value === 'allow');
				render();
			});
		});

		var reset = root.querySelector('[data-reset-consent]');
		if (reset) {
			reset.addEventListener('click', function () {
				if (typeof consent_api === 'undefined') {
					return;
				}

				[
					consent_api.cookie_prefix + '_marketing',
					consent_api.cookie_prefix + '_preferences',
					consent_api.cookie_prefix + '_consented_services'
				].forEach(function (name) {
					document.cookie = name + '=;expires=Thu, 01 Jan 1970 00:00:00 GMT;path=/';
				});

				render();
			});
		}

		document.addEventListener('wp_listen_for_consent_change', render);
		document.addEventListener('wp_consent_api_status_change_service', render);

		render();
	});
}());
	</script>
	<?php
}

/**
 * Render the demo page content.
 *
 * @return string
 */
function wp_consent_api_demo_shortcode(): string {
	wp_consent_api_demo_enqueue_script();

	$registered_cookies = function_exists( 'wp_get_cookie_info' ) ? wp_get_cookie_info() : array();

	ob_start();
	?>
	<style>
		.wp-consent-api-demo {
			color: #1e1e1e;
			font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
			margin: 0 auto;
			max-width: 980px;
			padding: 40px 20px;
		}

		.wp-consent-api-demo h1,
		.wp-consent-api-demo h2 {
			line-height: 1.2;
		}

		.wp-consent-api-demo__grid {
			display: grid;
			gap: 16px;
			grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
			margin: 24px 0;
		}

		.wp-consent-api-demo__panel {
			border: 1px solid #dcdcde;
			border-radius: 6px;
			padding: 18px;
		}

		.wp-consent-api-demo button {
			background: #1e1e1e;
			border: 0;
			border-radius: 4px;
			color: #fff;
			cursor: pointer;
			font: inherit;
			margin: 4px 6px 4px 0;
			padding: 8px 12px;
		}

		.wp-consent-api-demo pre {
			background: #f6f7f7;
			border: 1px solid #dcdcde;
			border-radius: 6px;
			overflow: auto;
			padding: 12px;
			white-space: pre-wrap;
		}

		.wp-consent-api-demo .is-allowed {
			color: #007017;
		}

		.wp-consent-api-demo .is-denied {
			color: #b32d2e;
		}
	</style>

	<div class="wp-consent-api-demo" data-wp-consent-api-demo>
		<h1><?php esc_html_e( 'WP Consent API Demo', 'wp-consent-api' ); ?></h1>
		<p>
			<?php esc_html_e( 'This Playground demo forces an opt-in consent type, registers two demo services, and lets you change category and service consent in the browser.', 'wp-consent-api' ); ?>
		</p>

		<div class="wp-consent-api-demo__grid">
			<section class="wp-consent-api-demo__panel">
				<h2><?php esc_html_e( 'Category consent', 'wp-consent-api' ); ?></h2>
				<p><?php esc_html_e( 'Marketing:', 'wp-consent-api' ); ?> <strong data-status="marketing">...</strong></p>
				<p><?php esc_html_e( 'Preferences:', 'wp-consent-api' ); ?> <strong data-status="preferences">...</strong></p>
				<button type="button" data-category-consent="marketing" data-value="allow"><?php esc_html_e( 'Allow marketing', 'wp-consent-api' ); ?></button>
				<button type="button" data-category-consent="marketing" data-value="deny"><?php esc_html_e( 'Deny marketing', 'wp-consent-api' ); ?></button>
				<button type="button" data-category-consent="preferences" data-value="allow"><?php esc_html_e( 'Allow preferences', 'wp-consent-api' ); ?></button>
				<button type="button" data-category-consent="preferences" data-value="deny"><?php esc_html_e( 'Deny preferences', 'wp-consent-api' ); ?></button>
			</section>

			<section class="wp-consent-api-demo__panel">
				<h2><?php esc_html_e( 'Service consent', 'wp-consent-api' ); ?></h2>
				<p><?php esc_html_e( 'Demo Analytics:', 'wp-consent-api' ); ?> <strong data-status="demo-analytics">...</strong></p>
				<p><?php esc_html_e( 'Explicitly denied:', 'wp-consent-api' ); ?> <strong data-status="demo-analytics-denied">...</strong></p>
				<button type="button" data-service-consent="Demo Analytics" data-value="allow"><?php esc_html_e( 'Allow Demo Analytics', 'wp-consent-api' ); ?></button>
				<button type="button" data-service-consent="Demo Analytics" data-value="deny"><?php esc_html_e( 'Deny Demo Analytics', 'wp-consent-api' ); ?></button>
			</section>
		</div>

		<section class="wp-consent-api-demo__panel">
			<h2><?php esc_html_e( 'Registered cookie info', 'wp-consent-api' ); ?></h2>
			<pre><?php echo esc_html( wp_json_encode( $registered_cookies, JSON_PRETTY_PRINT ) ); ?></pre>
		</section>

		<section class="wp-consent-api-demo__panel">
			<h2><?php esc_html_e( 'Browser cookies', 'wp-consent-api' ); ?></h2>
			<pre data-cookie-output>(none)</pre>
			<button type="button" data-reset-consent><?php esc_html_e( 'Reset demo consent', 'wp-consent-api' ); ?></button>
		</section>
	</div>
	<?php wp_consent_api_demo_print_script(); ?>
	<?php
	return (string) ob_get_clean();
}
add_shortcode( 'wp_consent_api_demo', 'wp_consent_api_demo_shortcode' );
