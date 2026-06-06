import './index.css';
import { createRoot } from '@wordpress/element';
import App from './components/App';

const root = document.getElementById( 'camaleaunmail-app' );
if ( root ) {
	createRoot( root ).render( <App /> );
}
