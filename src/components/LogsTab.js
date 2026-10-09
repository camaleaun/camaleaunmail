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
import LogDetail from './LogDetail';
import { STATUS_LABEL, StatusBadge, formatDate } from './logFormat';

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
	const [ openId,     setOpenId     ] = useState( null );
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

			<div className="cam-logs__table-wrap">
				<table className="cam-logs__table">
					<thead>
						<tr>
							<th scope="col">{ __( 'Date', 'camaleaunmail' ) }</th>
							<th scope="col">{ __( 'Status', 'camaleaunmail' ) }</th>
							<th scope="col">{ __( 'To', 'camaleaunmail' ) }</th>
							<th scope="col">{ __( 'Subject', 'camaleaunmail' ) }</th>
						</tr>
					</thead>
					<tbody>
						{ items.map( item => (
							<tr key={ item.id }>
								<td className="cam-logs__date">{ formatDate( item.created_at ) }</td>
								<td><StatusBadge status={ item.status } /></td>
								<td className="cam-logs__to">{ item.to_email }</td>
								<td className="cam-logs__subject">
									<button
										type="button"
										className="cam-logs__open"
										onClick={ () => setOpenId( item.id ) }
									>
										{ item.subject || __( '(no subject)', 'camaleaunmail' ) }
									</button>
								</td>
							</tr>
						) ) }
						{ ! items.length && ! loading && (
							<tr>
								<td colSpan={ 4 } className="cam-logs__empty">
									{ query || status
										? __( 'No emails match these filters.', 'camaleaunmail' )
										: __( 'No emails logged yet.', 'camaleaunmail' )
									}
								</td>
							</tr>
						) }
					</tbody>
				</table>
				{ loading && <div className="cam-logs__loading"><Spinner /></div> }
			</div>

			<div className="cam-logs__footer">
				<span className="cam-logs__count">
					{ sprintf(
						/* translators: %d: number of log entries */
						__( '%d emails', 'camaleaunmail' ),
						total
					) }
				</span>
				{ pages > 1 && (
					<div className="cam-logs__pagination">
						<Button
							variant="tertiary"
							disabled={ page <= 1 }
							onClick={ () => setPage( page - 1 ) }
							__next40pxDefaultSize
						>
							{ __( 'Previous', 'camaleaunmail' ) }
						</Button>
						<span>
							{ sprintf(
								/* translators: 1: current page, 2: total pages */
								__( 'Page %1$d of %2$d', 'camaleaunmail' ),
								page,
								pages
							) }
						</span>
						<Button
							variant="tertiary"
							disabled={ page >= pages }
							onClick={ () => setPage( page + 1 ) }
							__next40pxDefaultSize
						>
							{ __( 'Next', 'camaleaunmail' ) }
						</Button>
					</div>
				) }
			</div>

			{ openId && (
				<LogDetail
					id={ openId }
					onClose={ () => setOpenId( null ) }
					onChanged={ load }
					onNotice={ onNotice }
				/>
			) }

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
