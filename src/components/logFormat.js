import { __ } from '@wordpress/i18n';
import { dateI18n, getSettings } from '@wordpress/date';

export const STATUS_LABEL = {
	pending:         __( 'Pending',   'camaleaunmail' ),
	sent:            __( 'Sent',      'camaleaunmail' ),
	failed:          __( 'Failed',    'camaleaunmail' ),
	blocked:         __( 'Blocked',   'camaleaunmail' ),
	short_circuited: __( 'Intercepted', 'camaleaunmail' ),
};

export function StatusBadge( { status } ) {
	return (
		<span className={ `cam-status cam-status--${ status }` }>
			{ STATUS_LABEL[ status ] ?? status }
		</span>
	);
}

// The API returns GMT without a zone; mark it as UTC so the browser converts it.
export function parseDate( iso ) {
	return new Date( /[zZ]|[+-]\d\d:?\d\d$/.test( iso ) ? iso : iso + 'Z' );
}

// Date and time in the formats set in Settings > General, in the site's time zone.
export function formatDate( iso ) {
	if ( ! iso ) return '';
	const { formats } = getSettings();
	return dateI18n( formats.datetime, parseDate( iso ) );
}

// List time: the hour for today's emails, the day and month otherwise.
export function formatListDate( iso ) {
	if ( ! iso ) return '';
	const { formats } = getSettings();
	const date        = parseDate( iso );
	const now         = new Date();
	if ( dateI18n( 'Y-m-d', date ) === dateI18n( 'Y-m-d', now ) ) {
		return dateI18n( formats.time, date );
	}
	return dateI18n( dateI18n( 'Y', date ) === dateI18n( 'Y', now ) ? 'j M' : 'j M Y', date );
}

export function formatSender( log ) {
	return log.from_name ? `${ log.from_name } <${ log.from_email }>` : log.from_email;
}
