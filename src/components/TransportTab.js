import { Notice, ToggleControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import SmtpFields from './SmtpFields';
import TransportSelector from './TransportSelector';

export default function TransportTab( { settings, onChange, onBlur } ) {
	const transport = settings.transport ?? 'default';
	const forced    = !! settings._sending_disabled_by_constant;
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
				{ disabled && (
					<Notice status="warning" isDismissible={ false } className="cam-section__notice cam-sending-notice">
						{ __( 'Sending is disabled. WordPress reports emails as sent, but they are only recorded in Logs.', 'camaleaunmail' ) }
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
