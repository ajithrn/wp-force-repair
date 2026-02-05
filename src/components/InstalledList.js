const { useState, useEffect } = wp.element;
const apiFetch = wp.apiFetch;

const InstalledList = ( { type, onReinstall } ) => {
    // items will hold the specific list we are interested in
    const [ items, setItems ] = useState( [] );
    const [ loading, setLoading ] = useState( true );
    const [ error, setError ] = useState( null );
    
    // Selection state
    const [ selected, setSelected ] = useState( [] );

    useEffect( () => {
        setItems([]);
        setSelected([]);
        fetchInstalled();
    }, [ type ] );

    const fetchInstalled = async () => {
        setLoading( true );
        setError( null );
        try {
            const response = await apiFetch( { path: `/wp-force-repair/v1/installed?type=${type}` } );
            // Response structure is { plugins: [], themes: [] }
            // We only care about the one we asked for
            if ( type === 'plugin' ) {
                setItems( response.plugins );
            } else {
                setItems( response.themes );
            }
        } catch ( err ) {
            setError( err.message );
        }
        setLoading( false );
    };

    const handleDelete = async ( target ) => {
        if ( ! confirm( 'Are you sure you want to force delete this item? This cannot be undone.' ) ) {
            return;
        }

        try {
            await apiFetch( {
                path: '/wp-force-repair/v1/delete',
                method: 'POST',
                data: { type, target }
            } );
            // Refresh list
            fetchInstalled();
        } catch ( err ) {
            alert( 'Error deleting: ' + err.message );
        }
    };

    // --- Bulk Action Handlers ---

    const toggleSelectAll = ( e ) => {
        const isChecked = e.target.checked;
        if ( isChecked ) {
            const allIds = items.map( item => type === 'plugin' ? item.file : item.slug );
            setSelected( allIds );
        } else {
            setSelected( [] );
        }
    };

    const toggleSelection = ( id ) => {
        if ( selected.includes( id ) ) {
            setSelected( selected.filter( i => i !== id ) );
        } else {
            setSelected( [ ...selected, id ] );
        }
    };

    const handleBulkReinstall = async () => {
        if ( selected.length === 0 ) return;

        if ( ! confirm( `Are you sure you want to reinstall ${selected.length} items? This will process them sequentially.` ) ) {
            return;
        }

        // Process sequentially to be safe
        let index = 0;
        for ( const id of selected ) {
            index++;
            // Find item to get slug
            let slug = id;
            if ( type === 'plugin' ) {
                 const item = items.find( p => p.file === id );
                 if ( item ) slug = item.slug;
            } else {
                 // for themes, id is slug
            }
            
            // Trigger parent handler
            await onReinstall( slug, type, null, `${index}/${selected.length}` );
        }
        
        // Clear selection after done
        setSelected([]);
        fetchInstalled();
    };

    if ( loading ) return <div className="notice notice-info inline is-dismissible"><p>Loading {type}s...</p></div>;
    if ( error ) return <div className="notice notice-error inline is-dismissible"><p>Error: { error }</p></div>;

    const renderItem = ( item ) => {
        const id = type === 'plugin' ? item.file : item.slug;
        const isSelected = selected.includes( id );
        const hasUpdate = item.update_available;

        const cardStyle = {
            marginBottom: '6px', // Reduced margin
            borderLeft: hasUpdate ? '4px solid #f56e28' : '1px solid #c3c4c7', 
            position: 'relative'
        };

        return (
            <div key={ id } className="postbox wfr-item-row" style={ cardStyle }>
                <div className="inside" style={{ display: 'flex', alignItems: 'center', padding: '8px 10px' }}> {/* Reduced padding */}
                    <div style={{ marginRight: '10px' }}>
                         <input 
                            type="checkbox" 
                            checked={ isSelected } 
                            onChange={ () => toggleSelection( id ) }
                        />
                    </div>
                    <div style={{ flex: 1, minWidth: 0 }}> {/* minWidth fix for flex truncation */}
                        <div style={{ display: 'flex', alignItems: 'baseline', gap: '8px' }}>
                            <h3 style={{ margin: 0, fontSize: '14px', fontWeight: 600 }}> {/* Smaller font */}
                                { item.name } 
                            </h3>
                            <span style={{ color: '#666', fontSize: '11px' }}>v{ item.version }</span>
                            { hasUpdate && <span className="dashicons dashicons-update" style={{ color: '#f56e28', fontSize: '16px', lineHeight: 1 }} title="Update Available"></span> }
                            
                            <span 
                                style={{ 
                                    fontSize: '10px', 
                                    padding: '2px 6px', 
                                    borderRadius: '4px', 
                                    backgroundColor: item.source === 'repo' ? '#e5f5fa' : '#f0f0f1', 
                                    color: item.source === 'repo' ? '#0085ba' : '#50575e',
                                    border: '1px solid ' + (item.source === 'repo' ? '#b5e1ef' : '#c3c4c7'),
                                    lineHeight: 1.2,
                                    fontWeight: 500
                                }}
                            >
                                { item.source === 'repo' ? 'Repo' : 'External' }
                            </span>
                        </div>
                        
                        <div className="wfr-meta" style={{ fontSize: '12px', color: '#50575e', marginTop: '2px' }}>
                            { item.uri ? <a href={item.uri} target="_blank" rel="noopener noreferrer">Visit Site</a> : '' }
                            { item.uri && item.author ? ' | ' : '' }
                            { item.author ? <span dangerouslySetInnerHTML={{ __html: 'By ' + item.author }}></span> : '' }
                        </div>

                        { item.description && (
                            <p style={{ margin: '2px 0 0 0', color: '#646970', fontSize: '12px', whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis', maxWidth: '95%' }}>
                                {/* Single line description for compactness */}
                                { item.description.replace(/(<([^>]+)>)/gi, "") }
                            </p>
                        )}
                    </div>
                    <div className="wfr-row-actions" style={{ display: 'flex', gap: '5px', marginLeft: '10px' }}>
                        <button 
                            className="button button-small" /* Smaller buttons */
                            onClick={ () => onReinstall( item.slug, type, null ) }
                            style={{ height: '24px', lineHeight: '22px', padding: '0 8px', borderColor: hasUpdate ? '#f56e28' : undefined, color: hasUpdate ? '#d63638' : undefined }}
                        >
                            { hasUpdate ? 'Update Now' : 'Reinstall' }
                        </button>
                        <button 
                            className="button button-small button-link-delete"
                            style={{ color: '#b32d2e', textDecoration: 'none', height: '24px', lineHeight: '22px' }}
                            onClick={ () => handleDelete( id ) }
                        >
                            Delete
                        </button>
                    </div>
                </div>
            </div>
        );
    };

    return (
        <div className="wfr-view-container">
            <div className="wfr-section-header" style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginTop: '10px', marginBottom: '10px' }}>
                <h2 className="title" style={{ margin: 0 }}>Installed { type === 'plugin' ? 'Plugins' : 'Themes' }</h2>
                { selected.length > 0 && (
                    <button className="button button-secondary" onClick={ handleBulkReinstall }>
                        Force Update Selected ({selected.length})
                    </button>
                )}
            </div>
            
            <div className="wfr-bulk-bar" style={{ padding: '5px 0', borderBottom: '1px solid #e5e5e5', marginBottom: '15px' }}>
                 <label style={{ fontWeight: 600 }}>
                    <input type="checkbox" onChange={ toggleSelectAll } checked={ items.length > 0 && selected.length === items.length } /> Select All
                 </label>
            </div>

            <div className="wfr-list-container">
                { items.length === 0 && !loading && <p>No {type}s found.</p> }
                { items.map( ( item ) => renderItem( item ) ) }
            </div>
        </div>
    );
};

export default InstalledList;
