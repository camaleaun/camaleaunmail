import { __ } from '@wordpress/i18n';
import SmtpFields from './SmtpFields';
import TransportSelector from './TransportSelector';

export default function TransportTab( { settings, onChange, onBlur } ) {
	const transport = settings.transport ?? 'default';
	return (
		<>
			<TransportSelector
				value={ transport }
				onChange={ v => { onChange( 'transport', v ); onBlur?.(); } }
			/>
			{ transport === 'smtp' && <SmtpFields settings={ settings } onChange={ onChange } onBlur={ onBlur } /> }
		</>
	);
}
