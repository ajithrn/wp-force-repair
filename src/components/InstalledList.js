const { useState, useEffect } = wp.element;
const apiFetch = wp.apiFetch;

import { showSuccessToast, showErrorAlert, showConfirmDialog, showInputDialog, showUploadDialog, showReinstallDialog } from '../utils/notifications';

const InstalledList = ( { type, onReinstall } ) => {
    // items will hold the specific list we are interested in
    const [ items, setItems ] = useState( [] );
    const [ loading, setLoading ] = useState( true );
    const [ error, setError ] = useState( null );
    
    // Selection state
    const [ selected, setSelected ] = useState( [] );
    const [ filterSource, setFilterSource ] = useState( 'all' ); // 'all', 'repo', 'external'
    const [ bulkAction, setBulkAction ] = useState('');

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
        const result = await showConfirmDialog(
            'Force Delete?',
            'Are you sure you want to force delete this item? This cannot be undone.',
            'Yes, Delete It',
            'warning'
        );
        if ( ! result.isConfirmed ) return;

        try {
            await apiFetch( {
                path: '/wp-force-repair/v1/delete',
                method: 'POST',
                data: { type, target }
            } );
            // Refresh list
            showSuccessToast( `${type} deleted successfully.` );
            fetchInstalled();
        } catch ( err ) {
            showErrorAlert( 'Error deleting', err.message );
        }
    };

    const handleToggleStatus = async ( item ) => {
        const id = type === 'plugin' ? item.file : item.slug;
        const newAction = item.status === 'active' ? 'deactivate' : 'activate';
        
        // Confirmation for deactivation
        if ( newAction === 'deactivate' ) {
             const result = await showConfirmDialog(
                'Deactivate?',
                `Are you sure you want to deactivate ${item.name}?`,
                'Yes, Deactivate'
             );
             if ( ! result.isConfirmed ) return;
        }

        setLoading( true );
        try {
            await apiFetch( {
                path: '/wp-force-repair/v1/installed/toggle',
                method: 'POST',
                data: { type, slug: id, action: newAction }
            } );
            // Refresh list to reflect changes
            fetchInstalled();
        } catch ( err ) {
            showErrorAlert( 'Error changing status', err.message );
            setLoading( false );
        }
    };

    const handleReinstall = async ( item ) => {
        const defaultUrl = item.package || '';
        const result = await showReinstallDialog( item, defaultUrl );
        
        if ( result.isConfirmed && result.value ) {
            // result.value is object { mode: 'url'|'upload', url?: string, file?: File }
            if ( result.value.mode === 'url' ) {
                 await onReinstall( item.slug, type, result.value.url, '', 'install' );
            } else {
                 await onReinstall( item.slug, type, null, '', 'install', result.value.file );
            }
            fetchInstalled();
        }
    };

    const handleUpdate = async ( item ) => {
         const id = type === 'plugin' ? item.file : item.slug;
         // Use parent handler (onReinstall) which now supports 'update' action with visual overlay
         // Signature: handleInstall( slug, type, download_link, progress, action )
         await onReinstall( id, type, null, '', 'update' );
         
         // Since overlay handles success message and closing, we just need to refresh list when done?
         // Actually overlay doesn't auto-refresh list on close.
         // We might need a way to know when it closes or succeeds.
         // Current Dashboard implementation doesn't return anything or callback.
         // Ideally, Dashboard should accept an onComplete callback.
         // For now, user will close overlay, but list won't refresh automatically unless page reload or we poll.
         // Let's rely on manual refresh or add a listener if possible.
         // Wait, InstalledList mounts/unmounts? No.
         // We can't easily hook into overlay close from here without passing a callback down.
         // But for now this gives the VISUAL feedback requested.
         // I'll add a fetchInstalled() after a delay or just leave it manual.
         // Better: onReinstall is async, does it await the process?
         // Yes, handleInstall is async, but it returns once process STARTS? No, it awaits apiFetch.
         // So we can await it here, then fetchInstalled().
         
         fetchInstalled(); 
    };

    // --- Bulk Action Handlers ---

    const toggleSelectAll = ( e ) => {
        const isChecked = e.target.checked;
        if ( isChecked ) {
            // Only select visible (filtered) items
            const visibleIds = filteredItems.map( item => type === 'plugin' ? item.file : item.slug );
            setSelected( visibleIds );
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

    const handleBulkAction = async ( action ) => {
        if ( selected.length === 0 ) return;

        let actionLabel = action.charAt(0).toUpperCase() + action.slice(1);
        let confirmText = `Are you sure you want to ${action} ${selected.length} items?`;
        let confirmBtnText = `Yes, ${actionLabel} All`;
        let icon = 'question';

        if ( action === 'delete' ) {
            confirmText += ' This cannot be undone.';
            icon = 'warning';
        }

        const result = await showConfirmDialog(
            `Bulk ${actionLabel}?`,
            confirmText,
            confirmBtnText,
            icon
        );
        if ( ! result.isConfirmed ) return;

        // Reinstall and Update are special (client-side sequential for visual overlay)
        if ( action === 'reinstall' || action === 'update' ) {
            // Process sequentially to be safe
            let index = 0;
            for ( const id of selected ) {
                index++;
                // Find item to get slug
                let slug = id;
                if ( type === 'plugin' ) {
                     const item = items.find( p => p.file === id );
                     if ( item ) slug = item.slug;
                }
                
                // Trigger parent handler
                // If update, action is 'update', else 'install'
                const method = action === 'update' ? 'update' : 'install';
                await onReinstall( slug, type, null, `${index}/${selected.length}`, method );
            }
            
            // Clear selection after done
            setSelected([]);
            fetchInstalled();
            return;
        }

        // Standard actions via API
        setLoading( true );
        try {
            const res = await apiFetch( {
                path: '/wp-force-repair/v1/installed/bulk-action',
                method: 'POST',
                data: { 
                    type, 
                    action, 
                    slugs: selected 
                }
            } );
            
            if ( res.success ) {
                let msg = `Processed ${res.processed} items. Success: ${res.success_count}.`;
                if ( res.errors.length > 0 ) {
                    msg += ` Errors: ${res.errors.length}`;
                    showErrorAlert( 'Partial Success', msg + '\n' + res.errors.join('\n') );
                } else {
                    showSuccessToast( msg );
                }
            }
            
            setSelected([]);
            fetchInstalled();
        } catch ( err ) {
            showErrorAlert( 'Bulk Action Failed', err.message );
            setLoading( false );
        }
    };

    // Filter Logic
    const filteredItems = items.filter( item => {
        if ( filterSource === 'all' ) return true;
        return item.source === filterSource;
    });

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
                        {/* Status Toggle Button */}
                        <button 
                            className={`button button-small ${item.status === 'active' ? '' : 'button-primary'}`}
                            onClick={ () => handleToggleStatus( item ) }
                            disabled={ loading }
                            style={{ 
                                height: '24px', 
                                lineHeight: '22px', 
                                padding: '0 8px',
                                borderColor: item.status === 'active' ? '#d63638' : undefined,
                                color: item.status === 'active' ? '#d63638' : undefined
                            }}
                        >
                            { item.status === 'active' ? 'Deactivate' : 'Activate' }
                        </button>

                        {/* Smart Update Button Logic */}
                        { item.source === 'external' ? (
                            <>
                                { hasUpdate && (
                                     <button 
                                        className="button button-small"
                                        onClick={ () => handleUpdate( item ) }
                                        style={{ height: '24px', lineHeight: '22px', padding: '0 8px', borderColor: '#f56e28', color: '#d63638' }}
                                    >
                                        Update Now
                                    </button>
                                )}
                                <button 
                                    className="button button-small" 
                                    onClick={ () => handleReinstall( item ) }
                                    style={{ height: '24px', lineHeight: '22px', padding: '0 8px' }}
                                    title="Reinstall from URL or Zip"
                                >
                                    Reinstall
                                </button>
                            </>
                        ) : (
                            <>
                                { hasUpdate && (
                                     <button 
                                        className="button button-small"
                                        onClick={ () => handleUpdate( item ) }
                                        style={{ height: '24px', lineHeight: '22px', padding: '0 8px', borderColor: '#f56e28', color: '#d63638' }}
                                    >
                                        Update Now
                                    </button>
                                )}
                                <button 
                                    className="button button-small" 
                                    onClick={ () => onReinstall( item.slug, type, null ) }
                                    style={{ height: '24px', lineHeight: '22px', padding: '0 8px' }}
                                >
                                    Reinstall
                                </button>
                            </>
                        )}
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

    if ( loading ) return <div className="notice notice-info inline" style={{margin: '10px 0'}}><p>Loading {type}s...</p></div>;
    if ( error ) return <div className="notice notice-error inline" style={{margin: '10px 0'}}><p>Error: { error }</p></div>;



    return (
        <div className="wfr-view-container">
            <div className="wfr-section-header" style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginTop: '10px', marginBottom: '20px' }}>
                <div>
                    <h2 className="title" style={{ margin: 0 }}>Installed { type === 'plugin' ? 'Plugins' : 'Themes' }</h2>
                    <p className="description" style={{ marginTop: '5px' }}>
                        { type === 'plugin' ? 'Manage your installed plugins. Force re-install or delete them safely.' : 'Manage your installed themes. Force re-install or delete them safely.' }
                    </p>
                </div>
            </div>
            
            {/* Toolbar: Bulk Actions + Filter */}
            <div className="wfr-bulk-bar" style={{ padding: '10px', background: '#fff', border: '1px solid #c3c4c7', marginBottom: '15px', display: 'flex', alignItems: 'center', gap: '15px', flexWrap: 'wrap' }}>
                 <div style={{ display: 'flex', alignItems: 'center', gap: '10px' }}>
                    <select value={bulkAction} onChange={(e) => setBulkAction(e.target.value)} style={{ height: '30px', verticalAlign: 'middle' }}>
                        <option value="">Bulk Actions</option>
                        <option value="activate">Activate</option>
                        <option value="deactivate">Deactivate</option>
                        <option value="update">Update</option>
                        <option value="reinstall">Reinstall (Repo Only)</option>
                        <option value="delete">Delete</option>
                    </select>
                    <button 
                        className="button" 
                        onClick={ () => handleBulkAction(bulkAction) }
                        disabled={ !bulkAction || selected.length === 0 || loading }
                        style={{ height: '30px', lineHeight: '28px' }}
                    >
                        Apply
                    </button>
                    <span style={{ fontSize: '13px', color: '#666' }}>{ selected.length > 0 ? `${selected.length} selected` : '' }</span>
                 </div>

                 <div style={{ marginLeft: 'auto', display: 'flex', alignItems: 'center', gap: '5px' }}>
                    <label style={{ fontWeight: 500, fontSize: '13px' }}>Filter:</label>
                    <select 
                        value={ filterSource } 
                        onChange={ (e) => setFilterSource( e.target.value ) }
                        style={{ height: '30px', verticalAlign: 'middle' }}
                    >
                        <option value="all">All Sources</option>
                        <option value="repo">WordPress Repo</option>
                        <option value="external">External</option>
                    </select>
                </div>
            </div>

            <div className="wfr-bulk-select-all" style={{ padding: '0 0 10px 0' }}>
                 <label style={{ fontWeight: 600 }}>
                    <input type="checkbox" onChange={ toggleSelectAll } checked={ filteredItems.length > 0 && selected.length === filteredItems.length } /> Select All
                 </label>
            </div>

            <div className="wfr-list-container">
                { filteredItems.length === 0 ? (
                    <p>No {type}s found matching filter.</p>
                ) : (
                    filteredItems.map( ( item ) => renderItem( item ) )
                )}
            </div>
        </div>
    );
};

export default InstalledList;
