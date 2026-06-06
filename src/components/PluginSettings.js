/**
 * PluginSettings — cog icon button that opens a small settings dropdown.
 */
import { ToggleControl, Dropdown, Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { cog } from '@wordpress/icons';

export default function PluginSettings( { pluginSettings, onChange } ) {
	return (
		<Dropdown
			popoverProps={ { placement: 'bottom-end' } }
			renderToggle={ ( { isOpen, onToggle } ) => (
				<Button
					variant="tertiary"
					className="cam-cog-button"
					onClick={ onToggle }
					aria-expanded={ isOpen }
					icon={ cog }
					label={ __( 'Settings', 'camaleaunmail' ) }
					showTooltip
					__next40pxDefaultSize
				/>
			) }
			renderContent={ () => (
				<div className="cam-plugin-settings-popover">
					<p className="cam-plugin-settings-popover__title">
						{ __( 'Plugin settings', 'camaleaunmail' ) }
					</p>
					<ToggleControl
						label={ __( 'Clear settings on deactivation', 'camaleaunmail' ) }
						help={ pluginSettings.clear_on_deactivate
							? __( 'All settings will be deleted when the plugin is deactivated.', 'camaleaunmail' )
							: __( 'Settings are preserved when the plugin is deactivated.', 'camaleaunmail' )
						}
						checked={ !! pluginSettings.clear_on_deactivate }
						onChange={ v => onChange( 'clear_on_deactivate', v ) }
						__nextHasNoMarginBottom
					/>
				</div>
			) }
		/>
	);
}
