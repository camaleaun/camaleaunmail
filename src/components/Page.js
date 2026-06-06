import { useRef } from '@wordpress/element';
import { Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { cog, chevronLeft } from '@wordpress/icons';

export default function Page( {
	tabs,
	activeTab,
	onSelectTab,
	saveState,
	transport,
	canExport,
	exportFormat,
	onImport,
	page,
	onOpenPluginSettings,
	onBack,
	children,
} ) {
	const fileRef = useRef( null );
	const isMain  = page !== 'plugin-settings';

	// ── Export ────────────────────────────────────────────────────────────────
	function handleExport() {
		const nonce = window.camaleaunMailData?.nonce ?? '';
		const fmt   = exportFormat ?? 'yaml';
		const a     = document.createElement( 'a' );
		a.href      = '/wp-json/camaleaunmail/v1/export?format=' + fmt + '&_wpnonce=' + encodeURIComponent( nonce );
		a.download  = 'camaleaunmail-settings.' + ( fmt === 'json' ? 'json' : 'yml' );
		document.body.appendChild( a );
		a.click();
		document.body.removeChild( a );
	}

	// ── Import ────────────────────────────────────────────────────────────────
	function handleImportClick() {
		fileRef.current?.click();
	}

	async function handleFileChange( e ) {
		const file = e.target.files?.[ 0 ];
		if ( ! file ) return;
		e.target.value = '';
		const text    = await file.text();
		const isJson  = file.name.endsWith( '.json' ) || file.type === 'application/json';
		try {
			await window.wp.apiFetch( {
				path:   '/camaleaunmail/v1/import',
				method: 'POST',
				data:   isJson ? { json: text } : { yaml: text },
			} );
			onImport?.();
		} catch ( err ) {
			// eslint-disable-next-line no-alert
			window.alert( err?.message ?? __( 'Import failed.', 'camaleaunmail' ) );
		}
	}

	// ── Save indicator ────────────────────────────────────────────────────────
	const saveLabel = {
		idle:   __( 'Auto save', 'camaleaunmail' ),
		saving: __( 'Saving…',  'camaleaunmail' ),
		saved:  __( 'Saved',    'camaleaunmail' ),
		error:  __( 'Error',    'camaleaunmail' ),
	}[ saveState ] ?? __( 'Auto save', 'camaleaunmail' );

	return (
		<div className="cam-page">

			<div className="cam-page__header">
				<div className="cam-page__header-inner">
					{ isMain ? (
						<h1 className="cam-page__title">
							{ __( 'Mail Settings', 'camaleaunmail' ) }
						</h1>
					) : (
						<div className="cam-page__back">
							<Button
								variant="tertiary"
								icon={ chevronLeft }
								onClick={ onBack }
								className="cam-back-button"
								__next40pxDefaultSize
							>
								{ __( 'Mail Settings', 'camaleaunmail' ) }
							</Button>
							<span className="cam-page__back-title">
								{ __( 'Plugin Settings', 'camaleaunmail' ) }
							</span>
						</div>
					) }

					<div className="cam-header-actions">
						{ /* Hidden file input — always in DOM so ref works */ }
						<input
							ref={ fileRef }
							type="file"
							accept=".yml,.yaml,.json"
							style={ { display: 'none' } }
							onChange={ handleFileChange }
						/>

						{ isMain && (
							<Button
								variant="tertiary"
								disabled={ ! canExport }
								onClick={ handleExport }
								__next40pxDefaultSize
							>
								{ __( 'Export', 'camaleaunmail' ) }
							</Button>
						) }

						{ isMain && (
							<Button
								variant="tertiary"
								onClick={ handleImportClick }
								__next40pxDefaultSize
							>
								{ __( 'Import', 'camaleaunmail' ) }
							</Button>
						) }

						{ isMain && (
							<Button
								variant="tertiary"
								icon={ cog }
								label={ __( 'Plugin settings', 'camaleaunmail' ) }
								showTooltip
								onClick={ onOpenPluginSettings }
								className="cam-cog-button"
								__next40pxDefaultSize
							/>
						) }

						{ isMain && (
							<Button
								variant="tertiary"
								disabled
								className={ `cam-autosave-btn cam-autosave-btn--${ saveState ?? 'idle' }` }
								__next40pxDefaultSize
							>
								{ saveLabel }
							</Button>
						) }
					</div>
				</div>

				{ isMain && (
					<nav className="cam-tabs" role="tablist">
						{ tabs.map( t => (
							<button
								key={ t.id }
								role="tab"
								aria-selected={ activeTab === t.id }
								className={ `cam-tab${ activeTab === t.id ? ' is-active' : '' }` }
								onClick={ () => onSelectTab( t.id ) }
							>
								{ t.label }
							</button>
						) ) }
					</nav>
				) }
			</div>

			<div className="cam-page__body">
				{ children }
			</div>

		</div>
	);
}
