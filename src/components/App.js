import { useState, useEffect, useCallback, useRef } from '@wordpress/element';
import { Spinner, Notice } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import apiFetch from '../api';
import Page from './Page';
import TransportTab from './TransportTab';
import SenderTab from './SenderTab';
import TestTab from './TestTab';
import PluginSettingsTab from './PluginSettingsTab';
import LogsTab from './LogsTab';

const TABS = [
	{ id: 'transport', label: __( 'Transport', 'camaleaunmail' ) },
	{ id: 'sender',    label: __( 'Sender',    'camaleaunmail' ) },
	{ id: 'test',      label: __( 'Test',      'camaleaunmail' ) },
	{ id: 'logs',      label: __( 'Logs',      'camaleaunmail' ) },
];

// Save after this much inactivity (no field changes).
const IDLE_DELAY = 3000; // ms

export default function App() {
	const TAB_IDS    = TABS.map( t => t.id );
	const hashToTab  = ( h ) => TAB_IDS.includes( h.replace( '#', '' ) ) ? h.replace( '#', '' ) : 'transport';
	const tabToHash  = ( t ) => t === 'transport' ? '' : '#' + t;

	const initHash = window.location.hash;
	const [ tab,            setTab            ] = useState(
		initHash === '#settings' ? 'transport' : hashToTab( initHash )
	);
	const [ page,           setPage           ] = useState(
		initHash === '#settings' ? 'plugin-settings' : 'main'
	);
	const [ settings,       setSettings       ] = useState( null );
	const [ pluginSettings, setPluginSettings ] = useState( { clear_on_deactivate: false } );
	const [ saveState,      setSaveState      ] = useState( 'idle' );
	const [ notice,         setNotice         ] = useState( null );


	const settingsRef  = useRef( null );
	const dirtyRef     = useRef( false );
	const idleTimer    = useRef( null );
	const savingRef    = useRef( false );

	useEffect( () => {
		apiFetch( { path: '/camaleaunmail/v1/settings' } )
			.then( data => { setSettings( data ); settingsRef.current = data; } )
			.catch( () => setNotice( { type: 'error', message: __( 'Could not load settings.', 'camaleaunmail' ) } ) );
		apiFetch( { path: '/camaleaunmail/v1/plugin-settings' } )
			.then( setPluginSettings )
			.catch( () => {} );
	}, [] );

	const doSave = useCallback( async () => {
		if ( savingRef.current || ! dirtyRef.current ) return;
		savingRef.current = true;
		dirtyRef.current  = false;
		setSaveState( 'saving' );
		try {
			const saved = await apiFetch( {
				path:   '/camaleaunmail/v1/settings',
				method: 'POST',
				data:   settingsRef.current,
			} );
			setSettings( prev => ( { ...prev, _has_custom_settings: saved?._has_custom_settings ?? prev._has_custom_settings } ) );
			setSaveState( 'saved' );
			setTimeout( () => setSaveState( 'idle' ), 2000 );
		} catch ( err ) {
			setSaveState( 'error' );
			setNotice( { type: 'error', message: err?.message ?? __( 'Could not save settings.', 'camaleaunmail' ) } );
		} finally {
			savingRef.current = false;
		}
	}, [] );

	// Called on every field change — schedules a save after IDLE_DELAY of inactivity.
	const set = useCallback( ( key, value ) => {
		// Update ref synchronously before scheduling the React state update
		// so doSave() always reads the latest value even if called immediately after.
		settingsRef.current = { ...settingsRef.current, [ key ]: value };
		setSettings( settingsRef.current );
		dirtyRef.current = true;

		// Reset the idle timer on every change.
		if ( idleTimer.current ) clearTimeout( idleTimer.current );
		idleTimer.current = setTimeout( doSave, IDLE_DELAY );
	}, [ doSave ] );

	// Called on field blur — save immediately if dirty.
	const onBlur = useCallback( () => {
		if ( ! dirtyRef.current ) return;
		if ( idleTimer.current ) clearTimeout( idleTimer.current );
		doSave();
	}, [ doSave ] );

	const handlePluginSettingChange = useCallback( async ( key, value ) => {
		const updated = { ...pluginSettings, [ key ]: value };
		setPluginSettings( updated );
		try {
			await apiFetch( {
				path:   '/camaleaunmail/v1/plugin-settings',
				method: 'POST',
				data:   updated,
			} );
		} catch ( err ) {
			setPluginSettings( pluginSettings );
			setNotice( { type: 'error', message: err?.message ?? __( 'Could not save plugin settings.', 'camaleaunmail' ) } );
		}
	}, [ pluginSettings ] );

	const handleImport = useCallback( () => {
		if ( idleTimer.current ) clearTimeout( idleTimer.current );
		dirtyRef.current = false;
		apiFetch( { path: '/camaleaunmail/v1/settings' } )
			.then( data => {
				setSettings( data );
				settingsRef.current = data;
				setNotice( { type: 'success', message: __( 'Settings imported.', 'camaleaunmail' ) } );
			} )
			.catch( () => setNotice( { type: 'error', message: __( 'Import succeeded but could not reload settings.', 'camaleaunmail' ) } ) );
	}, [] );

	const sendingDisabled = !! settings?.sending_disabled || !! settings?._sending_disabled_by_constant;

	if ( ! settings ) {
		return <div className="cam-loading"><Spinner /></div>;
	}

	return (
		<Page
			tabs={ TABS }
			activeTab={ tab }
			onSelectTab={ t => {
				const hash = tabToHash( t );
				if ( hash ) {
					window.location.hash = hash;
				} else {
					window.history.replaceState( null, '', window.location.pathname + window.location.search );
				}
				setTab( t );
			} }
			saveState={ saveState }
			sendingDisabled={ sendingDisabled }
			transport={ settings.transport }
			canExport={ !! settings._has_custom_settings || settings.transport !== 'default' }
			exportFormat={ pluginSettings.export_format ?? 'yaml' }
			onImport={ handleImport }
			page={ page }
			onOpenPluginSettings={ () => {
				window.location.hash = '#settings';
				setPage( 'plugin-settings' );
			} }
			onBack={ () => {
				const hash = tabToHash( tab );
				if ( hash ) {
					window.location.hash = hash;
				} else {
					window.history.replaceState( null, '', window.location.pathname + window.location.search );
				}
				setPage( 'main' );
			} }
		>
			{ notice && (
				<Notice
					status={ notice.type }
					isDismissible
					onDismiss={ () => setNotice( null ) }
				>
					{ notice.message }
				</Notice>
			) }

			{ page === 'plugin-settings' ? (
				<PluginSettingsTab
					pluginSettings={ pluginSettings }
					onChange={ handlePluginSettingChange }
						exportFormat={ pluginSettings.export_format ?? 'yaml' }
					onExportFormatChange={ v => handlePluginSettingChange( 'export_format', v ) }
				/>
			) : (
				<>
					{ tab === 'transport' && (
						<TransportTab settings={ settings } onChange={ set } onBlur={ onBlur } />
					) }
					{ tab === 'sender' && (
						<SenderTab settings={ settings } onChange={ set } onBlur={ onBlur } />
					) }
					{ tab === 'test' && (
						<TestTab transport={ settings.transport } sendingDisabled={ sendingDisabled } />
					) }
					{ tab === 'logs' && (
						<LogsTab
							loggingEnabled={ pluginSettings.logging_enabled !== false }
							onNotice={ setNotice }
						/>
					) }
				</>
			) }
		</Page>
	);
}
