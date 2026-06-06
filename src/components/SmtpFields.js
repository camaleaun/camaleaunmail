import {
	TextControl,
	SelectControl,
	ToggleControl,
	Notice,
	__experimentalVStack as VStack,
	__experimentalHStack as HStack,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';

const ENCRYPTION_OPTIONS = [
	{ label: __( 'None — port 25',          'camaleaunmail' ), value: 'none' },
	{ label: __( 'TLS / STARTTLS — port 587', 'camaleaunmail' ), value: 'tls'  },
	{ label: __( 'SSL / SMTPS — port 465',   'camaleaunmail' ), value: 'ssl'  },
];

const PORT_ENC = { 465: 'ssl', 587: 'tls', 25: 'none' };
const ENC_PORT = { ssl: 465, tls: 587, none: 25 };

function isMismatch( port, enc ) {
	const expected = PORT_ENC[ Number( port ) ];
	return expected !== undefined && expected !== enc;
}

export default function SmtpFields( { settings, onChange, onBlur } ) {
	const port    = settings.smtp_port ?? 25;
	const enc     = settings.smtp_encryption ?? 'none';

	function handlePortChange( raw ) {
		const p = parseInt( raw, 10 ) || 25;
		onChange( 'smtp_port', p );
		if ( PORT_ENC[ p ] ) onChange( 'smtp_encryption', PORT_ENC[ p ] );
	}

	function handleEncChange( v ) {
		onChange( 'smtp_encryption', v );
		if ( PORT_ENC[ port ] ) onChange( 'smtp_port', ENC_PORT[ v ] );
	}

	return (
		<section className="cam-section">
			<h2 className="cam-section__title">{ __( 'SMTP configuration', 'camaleaunmail' ) }</h2>

			{ isMismatch( port, enc ) && (
				<Notice status="warning" isDismissible={ false } className="cam-section__notice">
					{ __( 'Port and encryption are mismatched. Port 465 → SSL, port 587 → TLS, port 25 → None. Auto-corrected on send.', 'camaleaunmail' ) }
				</Notice>
			) }

			<VStack spacing={ 5 }>
				<TextControl
					label={ __( 'Host', 'camaleaunmail' ) }
					value={ settings.smtp_host ?? '' }
					onChange={ v => onChange( 'smtp_host', v ) }
					placeholder="smtp.example.com"
					__next40pxDefaultSize
					__nextHasNoMarginBottom
					onBlur={ onBlur }
				/>

				<HStack alignment="left" spacing={ 4 } wrap>
					<div className="cam-field cam-field--grow">
						<SelectControl
							label={ __( 'Encryption', 'camaleaunmail' ) }
							value={ enc }
							options={ ENCRYPTION_OPTIONS }
							onChange={ handleEncChange }
							__next40pxDefaultSize
							__nextHasNoMarginBottom
					onBlur={ onBlur }
						/>
					</div>
					<div className="cam-field cam-field--port">
						<TextControl
							label={ __( 'Port', 'camaleaunmail' ) }
							type="number"
							value={ port }
							onChange={ handlePortChange }
							__next40pxDefaultSize
							__nextHasNoMarginBottom
					onBlur={ onBlur }
						/>
					</div>
				</HStack>

				<ToggleControl
					label={ __( 'Authentication', 'camaleaunmail' ) }
					checked={ !! settings.smtp_auth }
					onChange={ v => { onChange( 'smtp_auth', v ); onBlur?.(); } }
					__nextHasNoMarginBottom
				/>

				{ settings.smtp_auth && (
					<>
						<TextControl
							label={ __( 'Username', 'camaleaunmail' ) }
							value={ settings.smtp_username ?? '' }
							onChange={ v => onChange( 'smtp_username', v ) }
							autoComplete="off"
							__next40pxDefaultSize
							__nextHasNoMarginBottom
					onBlur={ onBlur }
						/>
						<TextControl
							label={ __( 'Password', 'camaleaunmail' ) }
							type="password"
							value={ settings.smtp_password ?? '' }
							onChange={ v => onChange( 'smtp_password', v ) }
							help={ settings.smtp_password === '__REDACTED__'
								? __( 'Password saved. Leave blank to keep it.', 'camaleaunmail' )
								: undefined }
							autoComplete="new-password"
							__next40pxDefaultSize
							__nextHasNoMarginBottom
					onBlur={ onBlur }
						/>
					</>
				) }
			</VStack>
		</section>
	);
}
