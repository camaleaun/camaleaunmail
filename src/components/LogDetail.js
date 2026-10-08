import { useState, useEffect } from '@wordpress/element';
import {
	Button,
	ButtonGroup,
	Modal,
	Notice,
	Spinner,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import apiFetch from '../api';
import { StatusBadge, formatDate } from './logFormat';

function isHtml( log ) {
	if ( log.content_type ) return log.content_type === 'text/html';
	return /<\/?(html|body|p|div|br|table|a)\b/i.test( log.message ?? '' );
}

export default function LogDetail( { id, onClose, onChanged, onNotice } ) {
	const [ log,     setLog     ] = useState( null );
	const [ error,   setError   ] = useState( null );
	const [ view,    setView    ] = useState( 'preview' ); // 'preview' | 'source' | 'headers'
	const [ busy,    setBusy    ] = useState( false );

	useEffect( () => {
		apiFetch( { path: `/camaleaunmail/v1/logs/${ id }` } )
			.then( setLog )
			.catch( err => setError( err?.message ?? __( 'Could not load the email.', 'camaleaunmail' ) ) );
	}, [ id ] );

	async function handleResend() {
		setBusy( true );
		try {
			const res = await apiFetch( { path: `/camaleaunmail/v1/logs/${ id }/resend`, method: 'POST' } );
			onNotice?.( {
				type:    res.blocked ? 'warning' : 'success',
				message: res.blocked
					? __( 'Not sent: sending is disabled. Recorded in Logs.', 'camaleaunmail' )
					: __( 'Email sent again.', 'camaleaunmail' ),
			} );
			onChanged?.();
			onClose();
		} catch ( err ) {
			setError( err?.message ?? __( 'Could not resend the email.', 'camaleaunmail' ) );
			onChanged?.();
		} finally {
			setBusy( false );
		}
	}

	async function handleDelete() {
		setBusy( true );
		try {
			await apiFetch( { path: `/camaleaunmail/v1/logs/${ id }`, method: 'DELETE' } );
			onChanged?.();
			onClose();
		} catch ( err ) {
			setError( err?.message ?? __( 'Could not delete the email.', 'camaleaunmail' ) );
			setBusy( false );
		}
	}

	const html = log ? isHtml( log ) : false;

	const meta = log ? [
		[ __( 'Date',        'camaleaunmail' ), formatDate( log.created_at ) ],
		[ __( 'From',        'camaleaunmail' ), log.from_name ? `${ log.from_name } <${ log.from_email }>` : log.from_email ],
		[ __( 'To',          'camaleaunmail' ), log.to_email ],
		[ __( 'Cc',          'camaleaunmail' ), log.cc ],
		[ __( 'Bcc',         'camaleaunmail' ), log.bcc ],
		[ __( 'Transport',   'camaleaunmail' ), log.transport ],
		[ __( 'Attachments', 'camaleaunmail' ), ( log.attachments ?? [] ).join( ', ' ) ],
	].filter( ( [ , value ] ) => value ) : [];

	return (
		<Modal
			title={ log?.subject || __( 'Email', 'camaleaunmail' ) }
			onRequestClose={ onClose }
			className="cam-log-modal"
			size="large"
		>
			{ error && (
				<Notice status="error" isDismissible onDismiss={ () => setError( null ) } className="cam-notice">
					{ error }
				</Notice>
			) }

			{ ! log && ! error && <div className="cam-loading"><Spinner /></div> }

			{ log && (
				<>
					<div className="cam-log-modal__status">
						<StatusBadge status={ log.status } />
						{ log.error && <span className="cam-log-modal__error">{ log.error }</span> }
					</div>

					<dl className="cam-log-modal__meta">
						{ meta.map( ( [ label, value ] ) => (
							<div key={ label } className="cam-log-modal__meta-row">
								<dt>{ label }</dt>
								<dd>{ value }</dd>
							</div>
						) ) }
					</dl>

					<div className="cam-body-header">
						<span className="cam-body-label">{ __( 'Message', 'camaleaunmail' ) }</span>
						<ButtonGroup className="cam-mode-toggle">
							<Button
								size="small"
								variant={ view === 'preview' ? 'primary' : 'secondary' }
								onClick={ () => setView( 'preview' ) }
							>
								{ html ? __( 'HTML', 'camaleaunmail' ) : __( 'Plain text', 'camaleaunmail' ) }
							</Button>
							{ html && (
								<Button
									size="small"
									variant={ view === 'source' ? 'primary' : 'secondary' }
									onClick={ () => setView( 'source' ) }
								>
									{ __( 'Source', 'camaleaunmail' ) }
								</Button>
							) }
							<Button
								size="small"
								variant={ view === 'headers' ? 'primary' : 'secondary' }
								onClick={ () => setView( 'headers' ) }
							>
								{ __( 'Headers', 'camaleaunmail' ) }
							</Button>
						</ButtonGroup>
					</div>

					{ view === 'preview' && html && (
						// Empty sandbox: no scripts, forms or same-origin access from the email.
						<iframe
							title={ __( 'Email preview', 'camaleaunmail' ) }
							sandbox=""
							srcDoc={ log.message }
							className="cam-log-modal__frame"
						/>
					) }
					{ view === 'preview' && ! html && (
						<pre className="cam-log-modal__text">{ log.message }</pre>
					) }
					{ view === 'source' && (
						<pre className="cam-log-modal__text cam-log-modal__text--code">{ log.message }</pre>
					) }
					{ view === 'headers' && (
						<pre className="cam-log-modal__text cam-log-modal__text--code">
							{ log.headers || __( '(no headers)', 'camaleaunmail' ) }
						</pre>
					) }

					<div className="cam-log-modal__actions">
						<Button variant="secondary" isDestructive disabled={ busy } onClick={ handleDelete } __next40pxDefaultSize>
							{ __( 'Delete', 'camaleaunmail' ) }
						</Button>
						<Button variant="primary" isBusy={ busy } disabled={ busy } onClick={ handleResend } __next40pxDefaultSize>
							{ __( 'Resend', 'camaleaunmail' ) }
						</Button>
					</div>
				</>
			) }
		</Modal>
	);
}
