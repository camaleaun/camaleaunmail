import apiFetchLib from '@wordpress/api-fetch';

const data = window.camaleaunMailData ?? {};
if ( data.nonce ) {
	apiFetchLib.use( apiFetchLib.createNonceMiddleware( data.nonce ) );
}
if ( data.apiRoot ) {
	apiFetchLib.use( apiFetchLib.createRootURLMiddleware( data.apiRoot ) );
}

export default apiFetchLib;
