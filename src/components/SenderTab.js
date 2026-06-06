import {
	TextControl,
	__experimentalVStack as VStack,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';

export default function SenderTab( { settings, onChange, onBlur } ) {
	return (
		<div className="cam-tab-body">
			<p className="cam-tab-description">
				{ __( 'Name and email address that appear in the "From" field of every outgoing email, regardless of which transport is active.', 'camaleaunmail' ) }
			</p>
			<VStack spacing={ 4 }>
				<TextControl
					label={ __( 'From email', 'camaleaunmail' ) }
					type="email"
					value={ settings.from_email ?? '' }
					onChange={ v => onChange( 'from_email', v ) }
					placeholder="noreply@example.com"
					__next40pxDefaultSize
					__nextHasNoMarginBottom
				onBlur={ onBlur }
				/>
				<TextControl
					label={ __( 'From name', 'camaleaunmail' ) }
					value={ settings.from_name ?? '' }
					onChange={ v => onChange( 'from_name', v ) }
					placeholder={ __( 'My Site', 'camaleaunmail' ) }
					__next40pxDefaultSize
					__nextHasNoMarginBottom
				onBlur={ onBlur }
				/>
			</VStack>
		</div>
	);
}
