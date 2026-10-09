import { __ } from '@wordpress/i18n';

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

export function formatDate( iso ) {
	if ( ! iso ) return '';
	return parseDate( iso ).toLocaleString( undefined, { dateStyle: 'medium', timeStyle: 'short' } );
}

// List time: the hour for today's emails, the date otherwise.
export function formatListDate( iso ) {
	if ( ! iso ) return '';
	const date = parseDate( iso );
	const now  = new Date();
	if ( date.toDateString() === now.toDateString() ) {
		return date.toLocaleTimeString( undefined, { timeStyle: 'short' } );
	}
	return date.toLocaleDateString( undefined, {
		month: 'short',
		day:   'numeric',
		...( date.getFullYear() !== now.getFullYear() && { year: 'numeric' } ),
	} );
}

export function formatSender( log ) {
	return log.from_name ? `${ log.from_name } <${ log.from_email }>` : log.from_email;
}
