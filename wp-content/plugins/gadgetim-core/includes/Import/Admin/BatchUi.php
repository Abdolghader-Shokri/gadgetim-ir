<?php
/**
 * Shared progress runner for long admin jobs (import, LQIP): a form, a progress bar, a log,
 * and a small inline script that calls an admin-ajax action in batches.
 *
 * @package Gadgetim\Core
 */

declare( strict_types=1 );

namespace Gadgetim\Core\Import\Admin;

/**
 * AJAX contract: POST { action, nonce, op: start|continue|stop, job, …form fields }.
 * Response (wp_send_json_success): { job, total, done, finished, status, log: [ { level, message } ] }.
 */
final class BatchUi {

	private static bool $printed = false;

	/**
	 * Renders one runner.
	 *
	 * @param array $args 'id' (DOM id), 'action' (AJAX action), 'nonce_action', 'start' (button label),
	 *                    'fields' (callable printing extra form fields), 'fallback' (callable printing a
	 *                    no-JS alternative), 'description'.
	 */
	public static function render( array $args ): void {
		$id     = sanitize_html_class( (string) ( $args['id'] ?? 'gtm-batch' ) );
		$action = sanitize_key( (string) ( $args['action'] ?? '' ) );
		$nonce  = wp_create_nonce( (string) ( $args['nonce_action'] ?? $action ) );
		$start  = (string) ( $args['start'] ?? __( 'Start', 'gadgetim-core' ) );
		?>
		<div class="gtm-batch" id="<?php echo esc_attr( $id ); ?>" data-gtm-batch data-action="<?php echo esc_attr( $action ); ?>" data-nonce="<?php echo esc_attr( $nonce ); ?>">
			<form class="gtm-batch__form" method="post" action="#">
				<?php
				if ( is_callable( $args['fields'] ?? null ) ) {
					call_user_func( $args['fields'] );
				}
				?>
				<p class="gtm-batch__actions">
					<button type="submit" class="button button-primary gtm-batch__start"><?php echo esc_html( $start ); ?></button>
					<button type="button" class="button gtm-batch__stop" disabled><?php esc_html_e( 'Stop', 'gadgetim-core' ); ?></button>
				</p>
			</form>
			<div class="gtm-batch__progress" hidden>
				<progress class="gtm-batch__bar" max="100" value="0"></progress>
				<p class="gtm-batch__status" role="status" aria-live="polite"></p>
			</div>
			<pre class="gtm-batch__log" hidden></pre>
			<?php if ( is_callable( $args['fallback'] ?? null ) ) : ?>
				<details class="gtm-batch__fallback">
					<summary><?php esc_html_e( 'Without JavaScript', 'gadgetim-core' ); ?></summary>
					<?php call_user_func( $args['fallback'] ); ?>
				</details>
			<?php endif; ?>
		</div>
		<?php
		self::assets();
	}

	/**
	 * Prints the style and script once per page.
	 */
	public static function assets(): void {
		if ( self::$printed ) {
			return;
		}
		self::$printed = true;
		$strings = [
			'starting' => __( 'Starting…', 'gadgetim-core' ),
			'stopped'  => __( 'Stopped.', 'gadgetim-core' ),
			'failed'   => __( 'Request failed.', 'gadgetim-core' ),
			'done'     => __( 'Finished.', 'gadgetim-core' ),
			'confirm'  => __( 'Start now?', 'gadgetim-core' ),
		];
		echo '<style>' . self::css() . '</style>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static CSS.
		echo '<script type="application/json" id="gtm-batch-i18n">' . wp_json_encode( $strings, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE ) . '</script>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<script>' . self::js() . '</script>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static script.
	}

	/**
	 * Runner styles (logical properties).
	 */
	private static function css(): string {
		return '.gtm-batch{display:grid;gap:.75rem;min-width:min(100%,520px)}'
			. '.gtm-batch__form{display:grid;gap:.5rem}.gtm-batch__option{display:block}'
			. '.gtm-batch__field{display:grid;gap:.25rem}.gtm-batch__field input[type=text]{width:100%}'
			. '.gtm-batch__actions{margin:0;display:flex;gap:.5rem;align-items:center}'
			. '.gtm-batch__bar{width:100%;height:18px}.gtm-batch__status{margin:.25rem 0 0;color:#646970}'
			. '.gtm-batch__log{max-height:320px;overflow:auto;background:#1d2327;color:#f0f0f1;padding:.75rem 1rem;border-radius:6px;font-size:12px;line-height:1.5;white-space:pre-wrap;direction:ltr;text-align:start}'
			. '.gtm-batch__log .is-error{color:#f86368}.gtm-batch__log .is-warning{color:#f0b849}.gtm-batch__log .is-success{color:#4ab866}'
			. '.gtm-batch__fallback{color:#646970}.gtm-batch__fallback summary{cursor:pointer}';
	}

	/**
	 * Runner script: vanilla JS, no dependencies.
	 */
	private static function js(): string {
		return <<<'JS'
(function(){
	var i18n = {};
	try { i18n = JSON.parse(document.getElementById('gtm-batch-i18n').textContent) || {}; } catch (e) {}
	function esc(s){ return String(s).replace(/[&<>]/g, function(c){ return {'&':'&amp;','<':'&lt;','>':'&gt;'}[c]; }); }
	document.querySelectorAll('[data-gtm-batch]').forEach(function(root){
		var form = root.querySelector('.gtm-batch__form');
		var start = root.querySelector('.gtm-batch__start');
		var stop = root.querySelector('.gtm-batch__stop');
		var progress = root.querySelector('.gtm-batch__progress');
		var bar = root.querySelector('.gtm-batch__bar');
		var status = root.querySelector('.gtm-batch__status');
		var log = root.querySelector('.gtm-batch__log');
		var job = '', running = false, stopping = false;
		function append(lines){
			if (!lines || !lines.length) { return; }
			log.hidden = false;
			lines.forEach(function(l){
				var span = document.createElement('span');
				span.className = 'is-' + (l.level || 'info');
				span.textContent = (l.step ? '[' + l.step + '] ' : '') + (l.message || '');
				log.appendChild(span);
				log.appendChild(document.createTextNode('\n'));
			});
			log.scrollTop = log.scrollHeight;
		}
		function post(op, extra){
			var data = new FormData(op === 'start' ? form : undefined);
			data.set('action', root.getAttribute('data-action'));
			data.set('nonce', root.getAttribute('data-nonce'));
			data.set('op', op);
			if (job) { data.set('job', job); }
			if (extra) { Object.keys(extra).forEach(function(k){ data.set(k, extra[k]); }); }
			return fetch(window.ajaxurl || 'admin-ajax.php', { method: 'POST', credentials: 'same-origin', body: data })
				.then(function(r){ return r.json(); });
		}
		function finish(text){
			running = false; stopping = false;
			start.disabled = false; stop.disabled = true;
			if (text) { status.textContent = text; }
		}
		function step(res){
			if (!res || !res.success) {
				append([{ level: 'error', message: (res && res.data && res.data.message) || i18n.failed }]);
				finish(i18n.failed);
				return;
			}
			var d = res.data || {};
			job = d.job || job;
			append(d.log);
			var total = d.total || 0, done = d.done || 0;
			bar.max = total || 1;
			bar.value = total ? done : (d.finished ? 1 : 0);
			status.textContent = d.status || (done + ' / ' + total);
			if (d.finished) { finish(d.status || i18n.done); return; }
			if (stopping) { post('stop').then(function(){ finish(i18n.stopped); }); return; }
			post('continue').then(step).catch(function(){ append([{ level: 'error', message: i18n.failed }]); finish(i18n.failed); });
		}
		form.addEventListener('submit', function(ev){
			ev.preventDefault();
			if (running) { return; }
			var q = root.getAttribute('data-confirm');
			if (q && !window.confirm(q)) { return; }
			running = true; stopping = false; job = '';
			start.disabled = true; stop.disabled = false;
			progress.hidden = false; log.hidden = true; log.textContent = '';
			bar.value = 0; status.textContent = i18n.starting;
			post('start').then(step).catch(function(){ append([{ level: 'error', message: i18n.failed }]); finish(i18n.failed); });
		});
		stop.addEventListener('click', function(){ stopping = true; stop.disabled = true; });
	});
})();
JS;
	}
}
