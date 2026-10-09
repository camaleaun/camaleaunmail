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

export function formatDate( iso ) {
	if ( ! iso ) return '';
	// The API returns GMT without a zone; mark it as UTC so the browser converts it.
	const date = new Date( /[zZ]|[+-]\d\d:?\d\d$/.test( iso ) ? iso : iso + 'Z' );
	return date.toLocaleString();
}
