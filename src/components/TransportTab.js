import { Notice, ToggleControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import SmtpFields from './SmtpFields';
import TransportSelector from './TransportSelector';

export default function TransportTab( { settings, onChange, onBlur } ) {
	const transport = settings.transport ?? 'default';
	const forced    = !! settings._sending_disabled_by_constant;
	const isLocal   = !! settings._is_local;
	const onLocal   = settings.disable_on_local !== false;
	const byLocal   = onLocal && isLocal;
	const disabled  = forced || !! settings.sending_disabled;
	return (
		<>
			<section className="cam-section">
				<h2 className="cam-section__title">
					{ __( 'Sending', 'camaleaunmail' ) }
				</h2>
				<ToggleControl
					label={ __( 'Disable sending (log only)', 'camaleaunmail' ) }
					help={ forced
						? __( 'Forced on by the CAMALEAUNMAIL_DISABLE_SENDING constant in wp-config.php.', 'camaleaunmail' )
						: __( 'No email leaves this site. Every email is recorded in the Logs tab instead. Use it on staging and local copies.', 'camaleaunmail' )
					}
					checked={ disabled }
					disabled={ forced }
					onChange={ v => { onChange( 'sending_disabled', v ); onBlur?.(); } }
					__nextHasNoMarginBottom
				/>
				<div className="cam-sending-local">
					<ToggleControl
						label={ __( 'Disable on local sites', 'camaleaunmail' ) }
						help={ isLocal
							? __( 'This site is local, so no email is sent while this is on.', 'camaleaunmail' )
							: __( 'Addresses on localhost, 127.0.0.1, .local and .test, and sites with the "local" environment type.', 'camaleaunmail' )
						}
						checked={ onLocal }
						onChange={ v => { onChange( 'disable_on_local', v ); onBlur?.(); } }
						__nextHasNoMarginBottom
					/>
				</div>
				{ ( disabled || byLocal ) && (
					<Notice status="warning" isDismissible={ false } className="cam-section__notice cam-sending-notice">
						{ disabled
							? __( 'Sending is disabled. WordPress reports emails as sent, but they are only recorded in Logs.', 'camaleaunmail' )
							: __( 'Sending is disabled on this local site. WordPress reports emails as sent, but they are only recorded in Logs.', 'camaleaunmail' )
						}
					</Notice>
				) }
			</section>
			<TransportSelector
				value={ transport }
				onChange={ v => { onChange( 'transport', v ); onBlur?.(); } }
			/>
			{ transport === 'smtp' && <SmtpFields settings={ settings } onChange={ onChange } onBlur={ onBlur } /> }
		</>
	);
}
