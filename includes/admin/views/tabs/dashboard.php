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
		$critical = array( 'Login-Schutz', 'Formular-Schutz', 'Basis-Schutz', 'Ereignis-Protokoll' );
		return in_array( $row['label'], $critical, true );
	}
);

$tiles = array(
	array(
		'tab'  => 'modules',
		'title' => __( 'Module ein/aus', 'wiedenroth-technologies-sentrygate' ),
		'text'  => __( 'Schutz-Bausteine einschalten oder pausieren.', 'wiedenroth-technologies-sentrygate' ),
	),
	array(
		'tab'  => 'interface',
		'title' => __( 'Basis-Schutz', 'wiedenroth-technologies-sentrygate' ),
		'text'  => __( 'XML-RPC, Benutzer-Erraten, Datei-Editor.', 'wiedenroth-technologies-sentrygate' ),
	),
	array(
		'tab'  => 'challenge',
		'title' => __( 'Bot-Schutz', 'wiedenroth-technologies-sentrygate' ),
		'text'  => __( 'Symbolwahl beim Login und in Formularen, Sitzungscookie, Rate-Limit.', 'wiedenroth-technologies-sentrygate' ),
	),
);
?>

<section class="<?php echo esc_attr( $ui->cls( 'card' ) ); ?>">
	<?php
	$ui->heading(
		__( 'Status', 'wiedenroth-technologies-sentrygate' ),
		__( 'Grün = Schutz aktiv. Gelb = bitte prüfen. Rot = ausgeschaltet oder kritisch.', 'wiedenroth-technologies-sentrygate' ),
		__( 'Als Erstes die empfohlenen Module unter „Module ein/aus“ einschalten – danach die einzelnen Bereiche nach Bedarf fein einstellen.', 'wiedenroth-technologies-sentrygate' )
	);
	?>

	<div class="<?php echo esc_attr( $ui->cls( 'stats' ) ); ?>">
		<?php
		$total = (int) $counts['active'] + (int) $counts['warning'] + (int) $counts['inactive'];
		SentryGate_Admin::stat( $ui, (string) $total, __( 'Schutzbereiche', 'wiedenroth-technologies-sentrygate' ), 'shield' );
		SentryGate_Admin::stat( $ui, (string) $counts['active'], __( 'Aktiv / in Ordnung', 'wiedenroth-technologies-sentrygate' ), 'check', 'ok' );
		SentryGate_Admin::stat( $ui, (string) $counts['warning'], __( 'Hinweis / prüfen', 'wiedenroth-technologies-sentrygate' ), 'warn', 'warn' );
		SentryGate_Admin::stat( $ui, (string) $counts['inactive'], __( 'Aus oder riskant', 'wiedenroth-technologies-sentrygate' ), 'x', 'off' );
		?>
	</div>
</section>

<?php if ( $critical_off ) : ?>
	<?php
	$ui->notice(
		'warning',
		implode( ' ', array_map( static fn( $row ) => $row['label'] . ' – ' . $row['message'] . '.', $critical_off ) ),
		__( 'Empfehlung für den Start', 'wiedenroth-technologies-sentrygate' )
	);
	?>
<?php endif; ?>

<div class="<?php echo esc_attr( $ui->cls( 'card', 'flush' ) ); ?>">
	<?php $ui->heading( __( 'Sicherheitsstatus', 'wiedenroth-technologies-sentrygate' ), __( 'Alle Schutzbereiche auf einen Blick.', 'wiedenroth-technologies-sentrygate' ) ); ?>
	<div class="<?php echo esc_attr( $ui->cls( 'table-wrap' ) ); ?>">
		<table class="<?php echo esc_attr( $ui->cls( 'table' ) ); ?>">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Status', 'wiedenroth-technologies-sentrygate' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Bereich', 'wiedenroth-technologies-sentrygate' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Kurz erklärt', 'wiedenroth-technologies-sentrygate' ); ?></th>
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
	<?php $ui->heading( __( 'Als Nächstes', 'wiedenroth-technologies-sentrygate' ), __( 'Die wichtigsten Einstellungen in drei Schritten.', 'wiedenroth-technologies-sentrygate' ) ); ?>
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
	<?php esc_html_e( 'Nicht enthalten in dieser Version: automatische Updates, Malware-Scan und Multisite-Verwaltung.', 'wiedenroth-technologies-sentrygate' ); ?>
</p>
