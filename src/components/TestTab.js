import { useState } from '@wordpress/element';
import {
	TextareaControl,
	TextControl,
	Button,
	Notice,
	ButtonGroup,
	__experimentalVStack as VStack,
} from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import apiFetch from '../api';

const TRANSPORT_LABEL = {
	smtp:    'SMTP',
	gmail:   'Google Mail',
	default: 'default mail()',
};

const DEFAULT_PLAIN = 'This is a test email sent from WordPress. If you received it, your mail transport is configured correctly.';

const DEFAULT_HTML = `<h1>Test email</h1>
<p>This is a <strong>test email</strong> sent from WordPress.</p>
<p>If you received it, your mail transport is configured correctly.</p>`;

export default function TestTab( { transport } ) {
	const [ to,      setTo      ] = useState( '' );
	const [ subject, setSubject ] = useState( 'Test email from WordPress' );
	const [ mode,    setMode    ] = useState( 'plain' ); // 'plain' | 'html'
	const [ body,    setBody    ] = useState( DEFAULT_PLAIN );
	const [ sending, setSending ] = useState( false );
	const [ result,  setResult  ] = useState( null );

	const label = TRANSPORT_LABEL[ transport ] ?? transport;

	function handleModeSwitch( next ) {
		setMode( next );
		// Swap to the matching default only if the user hasn't edited the body.
		if ( next === 'html'  && body === DEFAULT_PLAIN ) setBody( DEFAULT_HTML );
		if ( next === 'plain' && body === DEFAULT_HTML  ) setBody( DEFAULT_PLAIN );
	}

	const handleSend = async () => {
		setSending( true );
		setResult( null );
		try {
			const res = await apiFetch( {
				path:   '/camaleaunmail/v1/test-send',
				method: 'POST',
				data:   { to, subject, body, mode },
			} );
			setResult( {
				type:    'success',
				message: sprintf(
					/* translators: %s: recipient email */
					__( 'Test email sent successfully to %s.', 'camaleaunmail' ),
					res.to
				),
			} );
		} catch ( err ) {
			setResult( {
				type:    'error',
				message: err?.message ?? __( 'Could not send the test email. Check your settings and try again.', 'camaleaunmail' ),
			} );
		} finally {
			setSending( false );
		}
	};

	return (
		<div className="cam-tab-body">
			<p className="cam-tab-description">
				{ transport === 'default'
					? __( 'No transport configured. Select SMTP or Google Mail in the Transport tab first.', 'camaleaunmail' )
					: sprintf(
						/* translators: %s: transport label */
						__( 'Send a test email using the current %s configuration.', 'camaleaunmail' ),
						label
					)
				}
			</p>

			{ result && (
				<Notice
					status={ result.type }
					isDismissible
					onDismiss={ () => setResult( null ) }
					className="cam-notice"
				>
					{ result.message }
				</Notice>
			) }

			<VStack spacing={ 4 }>
				<TextControl
					label={ __( 'Recipient', 'camaleaunmail' ) }
					type="email"
					value={ to }
					onChange={ setTo }
					placeholder="you@example.com"
					__next40pxDefaultSize
					__nextHasNoMarginBottom
				/>

				<TextControl
					label={ __( 'Subject', 'camaleaunmail' ) }
					value={ subject }
					onChange={ setSubject }
					__next40pxDefaultSize
					__nextHasNoMarginBottom
				/>

				<div>
					<div className="cam-body-header">
						<label className="cam-body-label">
							{ __( 'Body', 'camaleaunmail' ) }
						</label>
						<ButtonGroup className="cam-mode-toggle">
							<Button
								size="small"
								variant={ mode === 'plain' ? 'primary' : 'secondary' }
								onClick={ () => handleModeSwitch( 'plain' ) }
							>
								{ __( 'Plain text', 'camaleaunmail' ) }
							</Button>
							<Button
								size="small"
								variant={ mode === 'html' ? 'primary' : 'secondary' }
								onClick={ () => handleModeSwitch( 'html' ) }
							>
								{ __( 'HTML', 'camaleaunmail' ) }
							</Button>
						</ButtonGroup>
					</div>

					<TextareaControl
						value={ body }
						onChange={ setBody }
						rows={ 6 }
						className={ 'cam-body-textarea' + ( mode === 'html' ? ' cam-body-textarea--html' : '' ) }
						__nextHasNoMarginBottom
					/>
				</div>

				<div>
					<Button
						variant="primary"
						isBusy={ sending }
						disabled={ sending || ! to || transport === 'default' }
						onClick={ handleSend }
						__next40pxDefaultSize
					>
						{ sending
							? __( 'Sending…', 'camaleaunmail' )
							: __( 'Send test email', 'camaleaunmail' )
						}
					</Button>
				</div>
			</VStack>
		</div>
	);
}
