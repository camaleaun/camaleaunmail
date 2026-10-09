import { useState, useEffect, useCallback, useRef } from '@wordpress/element';
import {
	Button,
	Notice,
	SearchControl,
	SelectControl,
	Spinner,
	__experimentalConfirmDialog as ConfirmDialog,
} from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import apiFetch from '../api';
import LogPreview from './LogPreview';
import { STATUS_LABEL, StatusBadge, formatListDate, formatSender, parseDate } from './logFormat';

const STATUS_OPTIONS = [
	{ value: '', label: __( 'All statuses', 'camaleaunmail' ) },
	...Object.entries( STATUS_LABEL ).map( ( [ value, label ] ) => ( { value, label } ) ),
];

const PER_PAGE = 20;

export default function LogsTab( { loggingEnabled, onNotice } ) {
	const [ items,      setItems      ] = useState( [] );
	const [ total,      setTotal      ] = useState( 0 );
	const [ pages,      setPages      ] = useState( 0 );
	const [ page,       setPage       ] = useState( 1 );
	const [ status,     setStatus     ] = useState( '' );
	const [ search,     setSearch     ] = useState( '' );
	const [ query,      setQuery      ] = useState( '' );
	const [ loading,    setLoading    ] = useState( true );
	const [ selected,   setSelected   ] = useState( null );
	const [ confirming, setConfirming ] = useState( false );

	const requestRef = useRef( 0 );

	// Debounce the search box so typing doesn't fire a request per key.
	useEffect( () => {
		const t = setTimeout( () => {
			setQuery( search );
			setPage( 1 );
		}, 400 );
		return () => clearTimeout( t );
	}, [ search ] );

	const load = useCallback( async () => {
		const request = ++requestRef.current;
		setLoading( true );
		try {
			const res = await apiFetch( {
				path:  '/camaleaunmail/v1/logs?' + new URLSearchParams( { page, per_page: PER_PAGE, status, search: query } ),
				parse: false,
			} );
			const data = await res.json();
			// Ignore responses that arrive after a newer request.
			if ( request !== requestRef.current ) return;
			setItems( data );
			setTotal( parseInt( res.headers.get( 'X-WP-Total' ) ?? '0', 10 ) );
			setPages( parseInt( res.headers.get( 'X-WP-TotalPages' ) ?? '0', 10 ) );
		} catch ( err ) {
			onNotice?.( { type: 'error', message: err?.message ?? __( 'Could not load logs.', 'camaleaunmail' ) } );
		} finally {
			if ( request === requestRef.current ) setLoading( false );
		}
	}, [ page, status, query, onNotice ] );

	useEffect( () => { load(); }, [ load ] );

	// Keep an email open: the first one when nothing (or a missing one) is selected.
	useEffect( () => {
		if ( loading ) return;
		if ( ! items.some( item => item.id === selected ) ) {
			setSelected( items[ 0 ]?.id ?? null );
		}
	}, [ items, loading, selected ] );

	function handleDeleted( id ) {
		const index = items.findIndex( item => item.id === id );
		// Open the next email in the list (or the previous one at the end).
		setSelected( ( items[ index + 1 ] ?? items[ index - 1 ] )?.id ?? null );
		load();
	}

	async function handleClear() {
		setConfirming( false );
		try {
			await apiFetch( { path: '/camaleaunmail/v1/logs', method: 'DELETE' } );
			setPage( 1 );
			load();
			onNotice?.( { type: 'success', message: __( 'Logs cleared.', 'camaleaunmail' ) } );
		} catch ( err ) {
			onNotice?.( { type: 'error', message: err?.message ?? __( 'Could not clear logs.', 'camaleaunmail' ) } );
		}
	}

	return (
		<div className="cam-tab-body cam-logs">
			{ ! loggingEnabled && (
				<Notice status="warning" isDismissible={ false } className="cam-notice">
					{ __( 'Logging is turned off in the plugin settings. New emails are not being recorded.', 'camaleaunmail' ) }
				</Notice>
			) }

			<div className="cam-logs__toolbar">
				<SearchControl
					label={ __( 'Search logs', 'camaleaunmail' ) }
					placeholder={ __( 'Search recipient or subject', 'camaleaunmail' ) }
					value={ search }
					onChange={ setSearch }
					className="cam-logs__search"
					__nextHasNoMarginBottom
				/>
				<SelectControl
					label={ __( 'Status', 'camaleaunmail' ) }
					hideLabelFromVision
					value={ status }
					options={ STATUS_OPTIONS }
					onChange={ v => { setStatus( v ); setPage( 1 ); } }
					__next40pxDefaultSize
					__nextHasNoMarginBottom
				/>
				<div className="cam-logs__toolbar-actions">
					<Button variant="tertiary" onClick={ load } disabled={ loading } __next40pxDefaultSize>
						{ __( 'Refresh', 'camaleaunmail' ) }
					</Button>
					<Button
						variant="secondary"
						isDestructive
						disabled={ ! total }
						onClick={ () => setConfirming( true ) }
						__next40pxDefaultSize
					>
						{ __( 'Clear logs', 'camaleaunmail' ) }
					</Button>
				</div>
			</div>

			<section className="cam-mail" aria-label={ __( 'Email', 'camaleaunmail' ) }>
				<aside className="cam-mail__list" aria-label={ __( 'Logged emails', 'camaleaunmail' ) }>
					<div className="cam-mail__list-header">
						<h2>{ status ? STATUS_LABEL[ status ] : __( 'Sent', 'camaleaunmail' ) }</h2>
						<span className="cam-mail__count">{ total }</span>
					</div>

					<div className="cam-mail__items" role="list">
						{ items.map( item => (
							<div key={ item.id } role="listitem">
								<button
									type="button"
									className={ `cam-mail__item${ item.id === selected ? ' is-selected' : '' }` }
									aria-pressed={ item.id === selected }
									onClick={ () => setSelected( item.id ) }
								>
									<span className="cam-mail__item-subject">
										{ item.subject || __( '(no subject)', 'camaleaunmail' ) }
									</span>
									<span className="cam-mail__item-meta">
										<span className="cam-mail__item-from">{ formatSender( item ) || item.to_email }</span>
										<time dateTime={ parseDate( item.created_at ).toISOString() }>
											{ formatListDate( item.created_at ) }
										</time>
									</span>
									{ item.status !== 'sent' && (
										<span className="cam-mail__item-status">
											<StatusBadge status={ item.status } />
										</span>
									) }
								</button>
							</div>
						) ) }
						{ ! items.length && ! loading && (
							<p className="cam-mail__empty">
								{ query || status
									? __( 'No emails match these filters.', 'camaleaunmail' )
									: __( 'No emails logged yet.', 'camaleaunmail' )
								}
							</p>
						) }
						{ loading && <div className="cam-logs__loading"><Spinner /></div> }
					</div>

					{ pages > 1 && (
						<div className="cam-mail__pagination">
							<Button
								variant="tertiary"
								size="compact"
								disabled={ page <= 1 }
								onClick={ () => setPage( page - 1 ) }
							>
								{ __( 'Previous', 'camaleaunmail' ) }
							</Button>
							<span>
								{ sprintf(
									/* translators: 1: current page, 2: total pages */
									__( '%1$d of %2$d', 'camaleaunmail' ),
									page,
									pages
								) }
							</span>
							<Button
								variant="tertiary"
								size="compact"
								disabled={ page >= pages }
								onClick={ () => setPage( page + 1 ) }
							>
								{ __( 'Next', 'camaleaunmail' ) }
							</Button>
						</div>
					) }
				</aside>

				{ selected ? (
					<LogPreview
						key={ selected }
						id={ selected }
						onChanged={ load }
						onDeleted={ handleDeleted }
						onNotice={ onNotice }
					/>
				) : (
					<div className="cam-mail__preview cam-mail__preview--empty">
						<p>{ __( 'Select an email to read it.', 'camaleaunmail' ) }</p>
					</div>
				) }
			</section>

			<ConfirmDialog
				isOpen={ confirming }
				onConfirm={ handleClear }
				onCancel={ () => setConfirming( false ) }
				confirmButtonText={ __( 'Clear logs', 'camaleaunmail' ) }
			>
				{ __( 'Delete every logged email? This cannot be undone.', 'camaleaunmail' ) }
			</ConfirmDialog>
		</div>
	);
}
