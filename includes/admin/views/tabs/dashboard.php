<?php
/**
 * Dashboard tab – traffic lights & recommendations.
 *
 * @package SentryGate
 *
 * @var SentryGate_Admin_Ui $ui
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$statuses = SentryGate_Dashboard::all_statuses();
$counts   = array(
	'active'   => 0,
	'warning'  => 0,
	'inactive' => 0,
);

foreach ( $statuses as $row ) {
	if ( isset( $counts[ $row['status'] ] ) ) {
		++$counts[ $row['status'] ];
	}
}

$critical_off = array_filter(
	$statuses,
	static function ( $row ) {
		if ( 'inactive' !== $row['status'] ) {
			return false;
		}
		$critical = array( 'Login protection', 'Form protection', 'Basic protection', 'Audit log' );
		return in_array( $row['label'], $critical, true );
	}
);

$tiles = array(
	array(
		'tab'  => 'modules',
		'title' => __( 'Modules on/off', 'wiedenroth-technologies-sentrygate' ),
		'text'  => __( 'Enable or pause the protection building blocks.', 'wiedenroth-technologies-sentrygate' ),
	),
	array(
		'tab'  => 'interface',
		'title' => __( 'Basic protection', 'wiedenroth-technologies-sentrygate' ),
		'text'  => __( 'XML-RPC, username guessing, file editor.', 'wiedenroth-technologies-sentrygate' ),
	),
	array(
		'tab'  => 'challenge',
		'title' => __( 'Bot protection', 'wiedenroth-technologies-sentrygate' ),
		'text'  => __( 'Symbol choice at login and in forms, session cookie, rate limit.', 'wiedenroth-technologies-sentrygate' ),
	),
);
?>

<section class="<?php echo esc_attr( $ui->cls( 'card' ) ); ?>">
	<?php
	$ui->heading(
		__( 'Status', 'wiedenroth-technologies-sentrygate' ),
		__( 'Green = protection active. Yellow = please check. Red = off or critical.', 'wiedenroth-technologies-sentrygate' ),
		__( 'First, turn on the recommended modules under "Modules on/off" – then fine-tune each section as needed.', 'wiedenroth-technologies-sentrygate' )
	);
	?>

	<div class="<?php echo esc_attr( $ui->cls( 'stats' ) ); ?>">
		<?php
		$total = (int) $counts['active'] + (int) $counts['warning'] + (int) $counts['inactive'];
		SentryGate_Admin::stat( $ui, (string) $total, __( 'Protection sections', 'wiedenroth-technologies-sentrygate' ), 'shield' );
		SentryGate_Admin::stat( $ui, (string) $counts['active'], __( 'Active / OK', 'wiedenroth-technologies-sentrygate' ), 'check', 'ok' );
		SentryGate_Admin::stat( $ui, (string) $counts['warning'], __( 'Notice / check', 'wiedenroth-technologies-sentrygate' ), 'warn', 'warn' );
		SentryGate_Admin::stat( $ui, (string) $counts['inactive'], __( 'Off or risky', 'wiedenroth-technologies-sentrygate' ), 'x', 'off' );
		?>
	</div>
</section>

<?php if ( $critical_off ) : ?>
	<?php
	$ui->notice(
		'warning',
		implode( ' ', array_map( static fn( $row ) => $row['label'] . ' – ' . $row['message'] . '.', $critical_off ) ),
		__( 'Recommended first steps', 'wiedenroth-technologies-sentrygate' )
	);
	?>
<?php endif; ?>

<div class="<?php echo esc_attr( $ui->cls( 'card', 'flush' ) ); ?>">
	<?php $ui->heading( __( 'Security status', 'wiedenroth-technologies-sentrygate' ), __( 'All protection sections at a glance.', 'wiedenroth-technologies-sentrygate' ) ); ?>
	<div class="<?php echo esc_attr( $ui->cls( 'table-wrap' ) ); ?>">
		<table class="<?php echo esc_attr( $ui->cls( 'table' ) ); ?>">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Status', 'wiedenroth-technologies-sentrygate' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Section', 'wiedenroth-technologies-sentrygate' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Summary', 'wiedenroth-technologies-sentrygate' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $statuses as $row ) : ?>
					<tr>
						<td><?php SentryGate_Admin::status_badge( $ui, $row['status'] ); ?></td>
						<td><strong><?php echo esc_html( $row['label'] ); ?></strong></td>
						<td><?php echo esc_html( $row['message'] ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
</div>

<section class="<?php echo esc_attr( $ui->cls( 'card', 'intro' ) ); ?>">
	<?php $ui->heading( __( 'Next steps', 'wiedenroth-technologies-sentrygate' ), __( 'The most important settings in three steps.', 'wiedenroth-technologies-sentrygate' ) ); ?>
	<div class="<?php echo esc_attr( $ui->cls( 'tiles' ) ); ?>">
		<?php foreach ( $tiles as $tile ) : ?>
			<a class="<?php echo esc_attr( $ui->cls( 'tile' ) ); ?>" href="<?php echo esc_url( admin_url( 'admin.php?page=' . SENTRYGATE_SLUG . '&tab=' . $tile['tab'] ) ); ?>">
				<span class="<?php echo esc_attr( $ui->cls( 'tile__title' ) ); ?>"><?php echo esc_html( $tile['title'] ); ?></span>
				<span class="<?php echo esc_attr( $ui->cls( 'tile__text' ) ); ?>"><?php echo esc_html( $tile['text'] ); ?></span>
			</a>
		<?php endforeach; ?>
	</div>
</section>

<p class="<?php echo esc_attr( $ui->cls( 'card__text' ) ); ?>">
	<?php esc_html_e( 'Not included in this version: automatic updates, malware scanning, and Multisite management.', 'wiedenroth-technologies-sentrygate' ); ?>
</p>
