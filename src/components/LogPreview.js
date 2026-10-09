import { useState, useEffect } from '@wordpress/element';
import {
	Button,
	ButtonGroup,
	Notice,
	Spinner,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import apiFetch from '../api';
import { StatusBadge, formatDate, formatSender } from './logFormat';

function isHtml( log ) {
	if ( log.content_type ) return log.content_type === 'text/html';
	return /<\/?(html|body|p|div|br|table|a)\b/i.test( log.message ?? '' );
}

export default function LogPreview( { id, onChanged, onDeleted, onNotice } ) {
	const [ log,   setLog   ] = useState( null );
	const [ error, setError ] = useState( null );
	const [ view,  setView  ] = useState( 'preview' ); // 'preview' | 'source' | 'headers'
	const [ busy,  setBusy  ] = useState( false );

	useEffect( () => {
		let active = true;
		setLog( null );
		setError( null );
		setView( 'preview' );
		apiFetch( { path: `/camaleaunmail/v1/logs/${ id }` } )
			.then( data => { if ( active ) setLog( data ); } )
			.catch( err => { if ( active ) setError( err?.message ?? __( 'Could not load the email.', 'camaleaunmail' ) ); } );
		// Ignore a response for an email that is no longer selected.
		return () => { active = false; };
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
		} catch ( err ) {
			setError( err?.message ?? __( 'Could not resend the email.', 'camaleaunmail' ) );
		} finally {
			setBusy( false );
			onChanged?.();
		}
	}

	async function handleDelete() {
		setBusy( true );
		try {
			await apiFetch( { path: `/camaleaunmail/v1/logs/${ id }`, method: 'DELETE' } );
			onDeleted?.( id );
		} catch ( err ) {
			setError( err?.message ?? __( 'Could not delete the email.', 'camaleaunmail' ) );
		} finally {
			setBusy( false );
		}
	}

	if ( ! log ) {
		return (
			<div className="cam-mail__preview cam-mail__preview--empty">
				{ error
					? <Notice status="error" isDismissible={ false }>{ error }</Notice>
					: <Spinner />
				}
			</div>
		);
	}

	const html = isHtml( log );

	const left = [
		[ __( 'From:', 'camaleaunmail' ), formatSender( log ) ],
		[ __( 'To:',   'camaleaunmail' ), log.to_email ],
		[ __( 'Cc:',   'camaleaunmail' ), log.cc ],
		[ __( 'Bcc:',  'camaleaunmail' ), log.bcc ],
	].filter( ( [ , value ] ) => value );

	const right = [
		[ __( 'Sent:',        'camaleaunmail' ), formatDate( log.created_at ) ],
		[ __( 'Transport:',   'camaleaunmail' ), log.transport ],
		[ __( 'Attachments:', 'camaleaunmail' ), ( log.attachments ?? [] ).join( ', ' ) ],
	].filter( ( [ , value ] ) => value );

	return (
		<div className="cam-mail__preview">
			<div className="cam-mail__preview-header">
				<div className="cam-mail__preview-title">
					<h2>{ log.subject || __( '(no subject)', 'camaleaunmail' ) }</h2>
					<div className="cam-mail__preview-actions">
						<StatusBadge status={ log.status } />
						<Button variant="tertiary" isDestructive disabled={ busy } onClick={ handleDelete } size="compact">
							{ __( 'Delete', 'camaleaunmail' ) }
						</Button>
						<Button variant="secondary" isBusy={ busy } disabled={ busy } onClick={ handleResend } size="compact">
							{ __( 'Resend', 'camaleaunmail' ) }
						</Button>
					</div>
				</div>

				<div className="cam-mail__metadata">
					{ [ left, right ].map( ( column, i ) => (
						<div key={ i } className="cam-mail__metadata-column">
							{ column.map( ( [ label, value ] ) => (
								<span key={ label }><strong>{ label }</strong> { value }</span>
							) ) }
						</div>
					) ) }
				</div>

				{ log.error && <p className="cam-mail__error">{ log.error }</p> }
				{ error && (
					<Notice status="error" isDismissible onDismiss={ () => setError( null ) }>
						{ error }
					</Notice>
				) }
			</div>

			<div className="cam-mail__view">
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

			<hr className="cam-mail__divider" />

			{ view === 'preview' && html && (
				// Empty sandbox: no scripts, forms or same-origin access from the email.
				<iframe
					title={ __( 'Email preview', 'camaleaunmail' ) }
					sandbox=""
					srcDoc={ log.message }
					className="cam-mail__frame"
				/>
			) }
			{ view === 'preview' && ! html && (
				<pre className="cam-mail__text-body">{ log.message }</pre>
			) }
			{ view === 'source' && (
				<pre className="cam-mail__text-body cam-mail__text-body--code">{ log.message }</pre>
			) }
			{ view === 'headers' && (
				<pre className="cam-mail__text-body cam-mail__text-body--code">
					{ log.headers || __( '(no headers)', 'camaleaunmail' ) }
				</pre>
			) }
		</div>
	);
}
