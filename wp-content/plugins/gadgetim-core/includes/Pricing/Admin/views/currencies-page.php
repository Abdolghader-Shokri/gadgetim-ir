<?php
/**
 * Gadgetim → Currencies tab: progress panel, backup age, currency table, history.
 *
 * @var array $args { currencies, counts, modes, languages, history, backup, job, progress, last, page_url, sync_threshold, legacy_count }
 * @package Gadgetim\Core
 */

use Gadgetim\Core\Pricing\Admin\CurrenciesPage;
use Gadgetim\Core\Pricing\Calculator;
use Gadgetim\Core\Pricing\Currencies;
use Gadgetim\Core\Support\Options;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$gtm_currencies  = $args['currencies'];
$gtm_counts      = $args['counts'];
$gtm_modes       = $args['modes'];
$gtm_languages   = $args['languages'];
$gtm_history     = $args['history'];
$gtm_backup      = $args['backup'];
$gtm_job         = $args['job'];
$gtm_progress    = $args['progress'];
$gtm_last        = $args['last'];
$gtm_threshold   = (int) $args['sync_threshold'];
$gtm_legacy      = (int) $args['legacy_count'];
$gtm_date_format = (string) get_option( 'date_format' ) . ' ' . (string) get_option( 'time_format' );
$gtm_admin_post  = admin_url( 'admin-post.php' );

$gtm_user_name = static function ( int $user_id ): string {
	if ( $user_id <= 0 ) {
		return '—';
	}
	$user = get_userdata( $user_id );
	return $user ? (string) $user->display_name : sprintf( '#%d', $user_id );
};
?>
<div class="gtm-currencies" data-backup="<?php echo esc_attr( $gtm_backup['time'] > 0 ? ( $gtm_backup['stale'] ? 'stale' : 'ok' ) : 'unknown' ); ?>">

	<?php if ( is_array( $gtm_progress ) && ! $gtm_progress['finished'] ) : ?>
		<div class="notice notice-info inline gtm-progress" data-job="<?php echo esc_attr( $gtm_job ); ?>" data-total="<?php echo esc_attr( (string) $gtm_progress['total'] ); ?>">
			<p>
				<strong><?php esc_html_e( 'Applying rates…', 'gadgetim-core' ); ?></strong>
				<span class="gtm-progress__text">
					<?php
					printf(
						/* translators: 1: processed products, 2: total products */
						esc_html__( '%1$s of %2$s products recomputed…', 'gadgetim-core' ),
						esc_html( number_format_i18n( $gtm_progress['done'] ) ),
						esc_html( number_format_i18n( $gtm_progress['total'] ) )
					);
					?>
				</span>
			</p>
			<progress class="gtm-progress__bar" max="<?php echo esc_attr( (string) max( 1, $gtm_progress['total'] ) ); ?>" value="<?php echo esc_attr( (string) $gtm_progress['done'] ); ?>"></progress>
			<p class="description"><?php esc_html_e( 'The job runs in the background (Action Scheduler). You may leave this page; the result appears in the history below.', 'gadgetim-core' ); ?></p>
		</div>
	<?php elseif ( is_array( $gtm_progress ) && $gtm_progress['finished'] ) : ?>
		<div class="notice notice-success inline">
			<p>
				<?php
				printf(
					/* translators: 1: number of products, 2: number of failures */
					esc_html__( 'Done: %1$s products recomputed (%2$s failed). The page cache was purged once.', 'gadgetim-core' ),
					esc_html( number_format_i18n( $gtm_progress['done'] ) ),
					esc_html( number_format_i18n( $gtm_progress['failed'] ) )
				);
				?>
			</p>
		</div>
	<?php elseif ( '' !== $gtm_job ) : ?>
		<div class="notice notice-warning inline"><p><?php esc_html_e( 'This job is unknown or expired; check the history below.', 'gadgetim-core' ); ?></p></div>
	<?php endif; ?>

	<p class="gtm-currencies__backup<?php echo $gtm_backup['stale'] ? ' is-stale' : ''; ?>">
		<span class="dashicons <?php echo $gtm_backup['stale'] ? 'dashicons-warning' : 'dashicons-yes-alt'; ?>" aria-hidden="true"></span>
		<?php if ( $gtm_backup['time'] > 0 ) : ?>
			<?php
			printf(
				/* translators: 1: human time difference, 2: date */
				esc_html__( 'Last known backup: %1$s ago (%2$s).', 'gadgetim-core' ),
				esc_html( human_time_diff( $gtm_backup['time'] ) ),
				esc_html( date_i18n( $gtm_date_format, $gtm_backup['time'] ) )
			);
			?>
			<?php if ( $gtm_backup['stale'] ) : ?>
				<strong><?php esc_html_e( 'Older than 24 hours: take a backup before applying rates.', 'gadgetim-core' ); ?></strong>
			<?php endif; ?>
		<?php else : ?>
			<?php esc_html_e( 'No backup marker found: take a backup before applying rates.', 'gadgetim-core' ); ?>
			<span class="description"><?php echo esc_html( sprintf( /* translators: %s: file path */ __( '(backup.sh writes the time into %s)', 'gadgetim-core' ), $gtm_backup['path'] ) ); ?></span>
		<?php endif; ?>
	</p>

	<?php if ( $gtm_legacy > 0 ) : ?>
		<div class="notice notice-warning inline">
			<p>
				<?php
				printf(
					/* translators: %s: number of products */
					esc_html( _n( '%s product still carries the old plugin\'s currency meta. Run "Migrate legacy AED prices" on the Tools tab.', '%s products still carry the old plugin\'s currency meta. Run "Migrate legacy AED prices" on the Tools tab.', $gtm_legacy, 'gadgetim-core' ) ),
					esc_html( number_format_i18n( $gtm_legacy ) )
				);
				?>
			</p>
		</div>
	<?php endif; ?>

	<?php
	// A saved rate that is not applied yet: the call to action sits above the table, where it is
	// always visible (the table's own "Apply rates" column can fall outside narrow screens).
	foreach ( $gtm_currencies as $gtm_code => $gtm_cur ) :
		$gtm_count = (int) ( $gtm_counts[ $gtm_code ] ?? 0 );
		if ( empty( $gtm_cur['enabled'] ) || 0 === $gtm_count || ! Currencies::has_unapplied_change( $gtm_code ) || ( is_array( $gtm_progress ) && ! $gtm_progress['finished'] ) ) {
			continue;
		}
		?>
		<div class="notice notice-warning inline gtm-currencies__pending">
			<p>
				<strong>
					<?php
					printf(
						/* translators: 1: currency code, 2: number of products */
						esc_html__( 'The new %1$s rate is saved but not applied: the prices of %2$s products still use the previous rate.', 'gadgetim-core' ),
						'<code>' . esc_html( $gtm_code ) . '</code>', // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped.
						esc_html( number_format_i18n( $gtm_count ) )
					);
					?>
				</strong>
			</p>
			<p>
				<button type="submit" form="gtm-apply-<?php echo esc_attr( strtolower( $gtm_code ) ); ?>" class="button button-primary button-hero gtm-currencies__apply" data-code="<?php echo esc_attr( $gtm_code ); ?>" data-count="<?php echo esc_attr( (string) $gtm_count ); ?>">
					<?php
					/* translators: %s: currency code */
					echo esc_html( sprintf( __( 'Apply the %s rate to all products', 'gadgetim-core' ), $gtm_code ) );
					?>
				</button>
			</p>
		</div>
	<?php endforeach; ?>

	<form method="post" action="<?php echo esc_url( $gtm_admin_post ); ?>" id="gtm-currencies-form" class="gtm-currencies__form">
		<input type="hidden" name="action" value="<?php echo esc_attr( CurrenciesPage::SAVE_ACTION ); ?>">
		<?php wp_nonce_field( CurrenciesPage::SAVE_ACTION ); ?>

		<div class="gtm-currencies__scroll">
		<table class="widefat striped gtm-currencies__table">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Code', 'gadgetim-core' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Name', 'gadgetim-core' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Symbol', 'gadgetim-core' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Rate (Toman per unit)', 'gadgetim-core' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Rounding', 'gadgetim-core' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Step', 'gadgetim-core' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Decimals', 'gadgetim-core' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Enabled', 'gadgetim-core' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Products', 'gadgetim-core' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Updated', 'gadgetim-core' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Actions', 'gadgetim-core' ); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ( $gtm_currencies as $gtm_code => $gtm_cur ) : ?>
				<?php
				$gtm_count     = (int) ( $gtm_counts[ $gtm_code ] ?? 0 );
				$gtm_name      = 'currencies[' . $gtm_code . ']';
				$gtm_id        = 'gtm-cur-' . strtolower( $gtm_code );
				$gtm_enabled   = ! empty( $gtm_cur['enabled'] );
				$gtm_unapplied = Currencies::has_unapplied_change( $gtm_code );
				$gtm_is_charm  = Calculator::MODE_CHARM === $gtm_cur['rounding_mode'];
				?>
				<tr class="gtm-currencies__row<?php echo $gtm_enabled ? '' : ' is-disabled'; ?>" data-code="<?php echo esc_attr( $gtm_code ); ?>">
					<th scope="row">
						<code class="gtm-currencies__code"><?php echo esc_html( $gtm_code ); ?></code>
						<?php if ( $gtm_unapplied && $gtm_count > 0 ) : ?>
							<span class="gtm-badge gtm-badge--warn" title="<?php esc_attr_e( 'The saved rate was not applied to the products yet.', 'gadgetim-core' ); ?>"><?php esc_html_e( 'rate not applied', 'gadgetim-core' ); ?></span>
						<?php endif; ?>
					</th>
					<td class="gtm-currencies__labels">
						<?php foreach ( $gtm_languages as $gtm_lang => $gtm_language ) : ?>
							<label class="gtm-currencies__label-row">
								<span class="gtm-i18n__code"><?php echo esc_html( $gtm_lang ); ?></span>
								<input type="text" name="<?php echo esc_attr( $gtm_name . '[label][' . $gtm_lang . ']' ); ?>" value="<?php echo esc_attr( (string) ( $gtm_cur['label'][ $gtm_lang ] ?? '' ) ); ?>" class="regular-text" dir="<?php echo esc_attr( (string) ( $gtm_language['dir'] ?? 'rtl' ) ); ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: language name */ __( 'Name (%s)', 'gadgetim-core' ), Options::LANGUAGE_LABELS[ $gtm_lang ] ?? $gtm_lang ) ); ?>">
							</label>
						<?php endforeach; ?>
					</td>
					<td><input type="text" name="<?php echo esc_attr( $gtm_name . '[symbol]' ); ?>" value="<?php echo esc_attr( (string) $gtm_cur['symbol'] ); ?>" class="small-text" maxlength="8" dir="ltr"></td>
					<td>
						<input type="text" name="<?php echo esc_attr( $gtm_name . '[rate]' ); ?>" id="<?php echo esc_attr( $gtm_id . '-rate' ); ?>" value="<?php echo esc_attr( Currencies::rate_string( $gtm_cur ) ); ?>" class="regular-text gtm-currencies__rate" inputmode="decimal" dir="ltr" autocomplete="off">
					</td>
					<td>
						<select name="<?php echo esc_attr( $gtm_name . '[rounding_mode]' ); ?>" class="gtm-currencies__mode">
							<?php foreach ( $gtm_modes as $gtm_mode => $gtm_mode_label ) : ?>
								<option value="<?php echo esc_attr( $gtm_mode ); ?>" <?php selected( $gtm_cur['rounding_mode'], $gtm_mode ); ?>><?php echo esc_html( $gtm_mode_label ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
					<td>
						<input type="number" name="<?php echo esc_attr( $gtm_name . '[rounding_step]' ); ?>" value="<?php echo esc_attr( (string) $gtm_cur['rounding_step'] ); ?>" class="small-text gtm-currencies__step" min="1" step="1" <?php disabled( $gtm_is_charm ); ?> title="<?php esc_attr_e( 'Ignored by charm rounding.', 'gadgetim-core' ); ?>">
					</td>
					<td><input type="number" name="<?php echo esc_attr( $gtm_name . '[decimals]' ); ?>" value="<?php echo esc_attr( (string) $gtm_cur['decimals'] ); ?>" class="small-text" min="0" max="4" step="1"></td>
					<td>
						<label class="gtm-toggle" for="<?php echo esc_attr( $gtm_id . '-enabled' ); ?>">
							<input type="checkbox" class="gtm-toggle__input" id="<?php echo esc_attr( $gtm_id . '-enabled' ); ?>" name="<?php echo esc_attr( $gtm_name . '[enabled]' ); ?>" value="1" role="switch" <?php checked( $gtm_enabled ); ?>>
							<span class="gtm-toggle__track" aria-hidden="true"><span class="gtm-toggle__thumb"></span></span>
							<span class="screen-reader-text"><?php esc_html_e( 'Enabled', 'gadgetim-core' ); ?></span>
						</label>
					</td>
					<td class="gtm-currencies__count"><?php echo esc_html( number_format_i18n( $gtm_count ) ); ?></td>
					<td class="gtm-currencies__updated">
						<?php if ( $gtm_cur['updated_at'] > 0 ) : ?>
							<?php echo esc_html( date_i18n( $gtm_date_format, (int) $gtm_cur['updated_at'] ) ); ?><br>
							<span class="description"><?php echo esc_html( $gtm_user_name( (int) $gtm_cur['updated_by'] ) ); ?></span>
						<?php else : ?>
							—
						<?php endif; ?>
					</td>
					<td class="gtm-currencies__actions">
						<button type="submit" form="gtm-apply-<?php echo esc_attr( strtolower( $gtm_code ) ); ?>" class="button button-primary gtm-currencies__apply" data-code="<?php echo esc_attr( $gtm_code ); ?>" data-count="<?php echo esc_attr( (string) $gtm_count ); ?>" <?php disabled( ! $gtm_enabled || 0 === $gtm_count ); ?>>
							<?php esc_html_e( 'Apply rates', 'gadgetim-core' ); ?>
						</button>
						<?php if ( 0 === $gtm_count ) : ?>
							<button type="submit" form="gtm-delete-<?php echo esc_attr( strtolower( $gtm_code ) ); ?>" class="button-link button-link-delete gtm-currencies__delete"><?php esc_html_e( 'Delete', 'gadgetim-core' ); ?></button>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
			<tfoot>
				<tr class="gtm-currencies__new">
					<th scope="row"><input type="text" name="new[code]" value="" class="small-text" maxlength="3" placeholder="EUR" dir="ltr" autocapitalize="characters" aria-label="<?php esc_attr_e( 'New currency code', 'gadgetim-core' ); ?>"></th>
					<td><input type="text" name="new[label]" value="" class="regular-text" placeholder="<?php esc_attr_e( 'Name (Persian)', 'gadgetim-core' ); ?>" aria-label="<?php esc_attr_e( 'New currency name', 'gadgetim-core' ); ?>"></td>
					<td><input type="text" name="new[symbol]" value="" class="small-text" maxlength="8" dir="ltr" placeholder="€" aria-label="<?php esc_attr_e( 'New currency symbol', 'gadgetim-core' ); ?>"></td>
					<td><input type="text" name="new[rate]" value="" class="regular-text" inputmode="decimal" dir="ltr" placeholder="0" aria-label="<?php esc_attr_e( 'New currency rate', 'gadgetim-core' ); ?>"></td>
					<td colspan="3"><span class="description"><?php esc_html_e( 'New currencies start with charm rounding; change it after saving.', 'gadgetim-core' ); ?></span></td>
					<td>
						<label class="gtm-toggle" for="gtm-cur-new-enabled">
							<input type="checkbox" class="gtm-toggle__input" id="gtm-cur-new-enabled" name="new[enabled]" value="1" role="switch">
							<span class="gtm-toggle__track" aria-hidden="true"><span class="gtm-toggle__thumb"></span></span>
							<span class="screen-reader-text"><?php esc_html_e( 'Enabled', 'gadgetim-core' ); ?></span>
						</label>
					</td>
					<td colspan="3"><span class="description"><?php esc_html_e( 'Add a currency', 'gadgetim-core' ); ?></span></td>
				</tr>
			</tfoot>
		</table>
		</div>

		<p class="submit gtm-currencies__submit">
			<button type="submit" class="button button-primary"><?php esc_html_e( 'Save currencies', 'gadgetim-core' ); ?></button>
			<span class="description">
				<?php
				printf(
					/* translators: %s: number of products */
					esc_html__( 'Saving changes no product price. "Apply rates" re-saves the products of that currency: up to %s at once, larger sets in background batches of 50.', 'gadgetim-core' ),
					esc_html( number_format_i18n( $gtm_threshold ) )
				);
				?>
			</span>
		</p>
	</form>

	<?php foreach ( $gtm_currencies as $gtm_code => $gtm_cur ) : ?>
		<form method="post" action="<?php echo esc_url( $gtm_admin_post ); ?>" id="gtm-apply-<?php echo esc_attr( strtolower( $gtm_code ) ); ?>" class="gtm-currencies__hidden-form">
			<input type="hidden" name="action" value="<?php echo esc_attr( CurrenciesPage::APPLY_ACTION ); ?>">
			<input type="hidden" name="code" value="<?php echo esc_attr( $gtm_code ); ?>">
			<?php wp_nonce_field( CurrenciesPage::APPLY_ACTION . '_' . $gtm_code ); ?>
		</form>
		<form method="post" action="<?php echo esc_url( $gtm_admin_post ); ?>" id="gtm-delete-<?php echo esc_attr( strtolower( $gtm_code ) ); ?>" class="gtm-currencies__hidden-form">
			<input type="hidden" name="action" value="<?php echo esc_attr( CurrenciesPage::DELETE_ACTION ); ?>">
			<input type="hidden" name="code" value="<?php echo esc_attr( $gtm_code ); ?>">
			<?php wp_nonce_field( CurrenciesPage::DELETE_ACTION . '_' . $gtm_code ); ?>
		</form>
	<?php endforeach; ?>

	<h3 class="gtm-currencies__heading"><?php esc_html_e( 'Rate history', 'gadgetim-core' ); ?></h3>
	<?php if ( ! empty( $gtm_last['finished'] ) ) : ?>
		<p class="description">
			<?php
			printf(
				/* translators: 1: date, 2: currency code, 3: number of products */
				esc_html__( 'Last reprice: %1$s — %2$s, %3$s products.', 'gadgetim-core' ),
				esc_html( date_i18n( $gtm_date_format, (int) $gtm_last['finished'] ) ),
				esc_html( '' !== (string) ( $gtm_last['code'] ?? '' ) ? (string) $gtm_last['code'] : __( 'mixed (fix now)', 'gadgetim-core' ) ),
				esc_html( number_format_i18n( (int) ( $gtm_last['total'] ?? 0 ) ) )
			);
			?>
		</p>
	<?php endif; ?>
	<?php if ( ! $gtm_history ) : ?>
		<p class="description"><?php esc_html_e( 'No rate change recorded yet.', 'gadgetim-core' ); ?></p>
	<?php else : ?>
		<table class="widefat striped gtm-currencies__history">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'When', 'gadgetim-core' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Currency', 'gadgetim-core' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Old rate', 'gadgetim-core' ); ?></th>
					<th scope="col"><?php esc_html_e( 'New rate', 'gadgetim-core' ); ?></th>
					<th scope="col"><?php esc_html_e( 'By', 'gadgetim-core' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Products', 'gadgetim-core' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Applied', 'gadgetim-core' ); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ( $gtm_history as $gtm_entry ) : ?>
				<tr>
					<td><?php echo esc_html( date_i18n( $gtm_date_format, (int) ( $gtm_entry['time'] ?? 0 ) ) ); ?></td>
					<td><code><?php echo esc_html( (string) ( $gtm_entry['code'] ?? '' ) ); ?></code></td>
					<td dir="ltr"><?php echo esc_html( null === ( $gtm_entry['old'] ?? null ) ? '—' : number_format_i18n( (float) $gtm_entry['old'] ) ); ?></td>
					<td dir="ltr"><?php echo esc_html( number_format_i18n( (float) ( $gtm_entry['new'] ?? 0 ) ) ); ?></td>
					<td><?php echo esc_html( $gtm_user_name( (int) ( $gtm_entry['user'] ?? 0 ) ) ); ?></td>
					<td><?php echo esc_html( number_format_i18n( (int) ( $gtm_entry['affected'] ?? 0 ) ) ); ?></td>
					<td>
						<?php if ( ! empty( $gtm_entry['applied'] ) ) : ?>
							<span class="gtm-badge gtm-badge--ok"><?php echo esc_html( ! empty( $gtm_entry['applied_at'] ) ? date_i18n( $gtm_date_format, (int) $gtm_entry['applied_at'] ) : __( 'yes', 'gadgetim-core' ) ); ?></span>
						<?php else : ?>
							<span class="gtm-badge gtm-badge--warn"><?php esc_html_e( 'pending', 'gadgetim-core' ); ?></span>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>
