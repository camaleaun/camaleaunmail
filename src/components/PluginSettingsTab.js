import { ToggleControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

export default function PluginSettingsTab( { pluginSettings, onChange, exportFormat, onExportFormatChange } ) {
	const isJson        = exportFormat === 'json';
	const includeSchema = pluginSettings.include_schema !== false;
	const prettyJson    = pluginSettings.json_pretty_print !== false;
	const indentType    = pluginSettings.json_indent_type ?? 'tab';
	const indentSize    = pluginSettings.json_indent ?? 4;
	const isSpace       = indentType === 'space';

	return (
		<div className="cam-tab-body">
			<p className="cam-tab-description">
				{ __( 'Plugin-level options that control behaviour outside of mail transport.', 'camaleaunmail' ) }
			</p>

			<div className="cam-section">
				<h2 className="cam-section__title">
					{ __( 'Export', 'camaleaunmail' ) }
				</h2>

				<div className="cam-field">
					<ToggleControl
						label={ __( 'Include schema reference', 'camaleaunmail' ) }
						help={ __( 'Disable to omit the schema reference from the exported file.', 'camaleaunmail' ) }
						checked={ includeSchema }
						onChange={ v => onChange( 'include_schema', v ) }
						__nextHasNoMarginBottom
					/>
				</div>

				<div className="cam-field">
					<ToggleControl
						label={ __( 'Export in JSON format', 'camaleaunmail' ) }
						help={ isJson
							? __( 'Export format changed from YAML to JSON.', 'camaleaunmail' )
							: __( 'Default is YAML. Enable to export as JSON instead.', 'camaleaunmail' )
						}
						checked={ isJson }
						onChange={ v => onExportFormatChange?.( v ? 'json' : 'yaml' ) }
						__nextHasNoMarginBottom
					/>
				</div>

				{ isJson && (
					<>
						<div className="cam-field">
							<ToggleControl
								label={ __( 'Pretty-print JSON', 'camaleaunmail' ) }
								help={ prettyJson
									? __( 'Output is indented and human-readable.', 'camaleaunmail' )
									: __( 'Output is minified — smaller file, harder to read.', 'camaleaunmail' )
								}
								checked={ prettyJson }
								onChange={ v => onChange( 'json_pretty_print', v ) }
								__nextHasNoMarginBottom
							/>
						</div>

						{ prettyJson && (
							<>
								<div className="cam-field">
									<ToggleControl
										label={ __( 'Use tabs for indentation', 'camaleaunmail' ) }
										help={ __( 'Disable to switch indentation from tabs to spaces.', 'camaleaunmail' ) }
										checked={ indentType === 'tab' }
										onChange={ v => onChange( 'json_indent_type', v ? 'tab' : 'space' ) }
										__nextHasNoMarginBottom
									/>
								</div>

								{ isSpace && (
									<div className="cam-field">
										<ToggleControl
											label={ __( '4-space indent', 'camaleaunmail' ) }
											help={ __( 'Disable to switch indent size from 4 to 2 spaces.', 'camaleaunmail' ) }
											checked={ indentSize === 4 }
											onChange={ v => onChange( 'json_indent', v ? 4 : 2 ) }
											__nextHasNoMarginBottom
										/>
									</div>
								) }
							</>
						) }
					</>
				) }
			</div>

			<div className="cam-section">
				<h2 className="cam-section__title">
					{ __( 'Data', 'camaleaunmail' ) }
				</h2>
				<ToggleControl
					label={ __( 'Clear settings on deactivation', 'camaleaunmail' ) }
					help={ pluginSettings.clear_on_deactivate
						? __( 'All settings will be permanently deleted when the plugin is deactivated.', 'camaleaunmail' )
						: __( 'Settings are kept in the database when the plugin is deactivated or updated.', 'camaleaunmail' )
					}
					checked={ !! pluginSettings.clear_on_deactivate }
					onChange={ v => onChange( 'clear_on_deactivate', v ) }
					__nextHasNoMarginBottom
				/>
			</div>
		</div>
	);
}
