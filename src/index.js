import { render } from '@wordpress/element';
import Dashboard from './components/Dashboard';
import './style.css';

const App = () => {
    return <Dashboard />;
};

document.addEventListener( 'DOMContentLoaded', () => {
    const container = document.getElementById( 'wfr-dashboard' );
    if ( container ) {
        render( <App />, container );
    }
} );
