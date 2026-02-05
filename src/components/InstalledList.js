import { useState, useEffect } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';

const InstalledList = () => {
    const [ items, setItems ] = useState( { plugins: [], themes: [] } );
    const [ loading, setLoading ] = useState( true );
    const [ error, setError ] = useState( null );

    useEffect( () => {
        fetchInstalled();
    }, [] );

    const fetchInstalled = async () => {
        setLoading( true );
        try {
            const response = await apiFetch( { path: '/force-update/v1/installed' } );
            setItems( response );
        } catch ( err ) {
            setError( err.message );
        }
        setLoading( false );
    };

    const handleDelete = async ( type, target ) => {
        if ( ! confirm( 'Are you sure you want to force delete this item? This cannot be undone.' ) ) {
            return;
        }

        try {
            await apiFetch( {
                path: '/force-update/v1/delete',
                method: 'POST',
                data: { type, target }
            } );
            // Refresh list
            fetchInstalled();
        } catch ( err ) {
            alert( 'Error deleting: ' + err.message );
        }
    };

    if ( loading ) return <div className="wfr-loading">Loading installed items...</div>;
    if ( error ) return <div className="wfr-error">Error: { error }</div>;

    return (
        <div className="wfr-view-container">
            <h2 className="wfr-title">Installed Items</h2>
            
            <h3 className="wfr-subtitle">Plugins</h3>
            <div className="wfr-results-grid">
                { items.plugins.map( ( plugin ) => (
                    <div key={ plugin.file } className="wfr-card wfr-item-card">
                        <h4>{ plugin.name }</h4>
                        <div className="wfr-meta">
                            <span>v{ plugin.version }</span>
                        </div>
                        <div className="wfr-card-actions">
                            <button 
                                className="wfr-btn wfr-btn-danger wfr-btn-sm"
                                onClick={ () => handleDelete( 'plugin', plugin.file ) }
                            >
                                Delete
                            </button>
                        </div>
                    </div>
                ) ) }
            </div>

            <h3 className="wfr-subtitle" style={{marginTop: '2rem'}}>Themes</h3>
             <div className="wfr-results-grid">
                { items.themes.map( ( theme ) => (
                    <div key={ theme.slug } className="wfr-card wfr-item-card">
                        <h4>{ theme.name }</h4>
                        <div className="wfr-meta">
                            <span>v{ theme.version }</span>
                        </div>
                        <div className="wfr-card-actions">
                             <button 
                                className="wfr-btn wfr-btn-danger wfr-btn-sm"
                                onClick={ () => handleDelete( 'theme', theme.slug ) }
                            >
                                Delete
                            </button>
                        </div>
                    </div>
                ) ) }
            </div>
        </div>
    );
};

export default InstalledList;
