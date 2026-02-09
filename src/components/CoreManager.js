const { useState, useEffect } = wp.element;
const apiFetch = wp.apiFetch;
import InstallerOverlay from './InstallerOverlay';
import FileNode from './FileNode';
import QuarantineItem from './QuarantineItem';
import Swal from 'sweetalert2';
import withReactContent from 'sweetalert2-react-content';
import { showSuccessToast, showErrorAlert } from '../utils/notifications';

const MySwal = withReactContent(Swal);

const CoreManager = () => {
    // ... state ...
    const [ status, setStatus ] = useState( null );
    const [ loading, setLoading ] = useState( true );
    const [ scanning, setScanning ] = useState( false ); // Global scanning state
    
    // Tree State
    const [ fileCache, setFileCache ] = useState( {} ); // path -> files[]
    const [ expandedPaths, setExpandedPaths ] = useState( [] ); // array of paths
    const [ selectedFiles, setSelectedFiles ] = useState( [] );
    const [ quarantinedData, setQuarantinedData ] = useState( [] );
    
    // Installer Overlay State
    const [ isInstalling, setIsInstalling ] = useState( false );
    const [ installLogs, setInstallLogs ] = useState( [] );
    const [ installStatus, setInstallStatus ] = useState( 'processing' ); 
    const [ installMessage, setInstallMessage ] = useState( '' );

    useEffect( () => {
        fetchStatus();
        fetchPath(''); // Initial root scan
        fetchQuarantined(); 
    }, [] );

    // ... fetch functions ...
    const fetchStatus = async () => {
        try {
            const data = await apiFetch( { path: '/wp-force-repair/v1/core/status' } );
            setStatus( data );
        } catch ( e ) {
            console.error( e );
        }
        setLoading( false );
    };

    const fetchPath = async ( path ) => {
        // If we already have it, do we re-fetch? Let's re-fetch to be fresh.
        // But for UI responsiveness, we could check cache. 
        // For now, let's just fetch.
        if ( path === '' ) setScanning( true );
        
        try {
            const data = await apiFetch( { 
                path: '/wp-force-repair/v1/core/scan', 
                method: 'POST',
                data: { path: path } 
            } );
            
            setFileCache( prev => ({ ...prev, [path]: data.files || [] }) );
        } catch ( e ) {
            console.error( e );
            showErrorAlert( 'Error', 'Failed to scan directory: ' + path );
        }
        
        if ( path === '' ) setScanning( false );
    };

    const toggleFolder = async ( path ) => {
        if ( expandedPaths.includes( path ) ) {
            setExpandedPaths( expandedPaths.filter( p => p !== path ) );
        } else {
            setExpandedPaths( [ ...expandedPaths, path ] );
            // Fetch if not in cache (or maybe always refresh?)
            // Let's check cache to save calls
            if ( ! fileCache[path] ) {
                await fetchPath( path );
            }
        }
    };
    
    const refreshCurrentView = () => {
        // Refresh root and all expanded paths
        fetchPath('');
        expandedPaths.forEach( path => fetchPath( path ) );
    };

    const fetchQuarantined = async () => {
        try {
            const data = await apiFetch( { path: '/wp-force-repair/v1/core/quarantine/list' } );
            setQuarantinedData( data || [] );
        } catch ( e ) {
            console.error( e );
        }
    };

    const toggleSelectAll = ( e ) => {
        // Select All ROOT files only for simplicity? 
        // Or recursively select all *visible*?
        // Let's stick to Root Files for "Select All" to avoid massive recursion issues
        if ( e.target.checked ) {
            const rootFiles = fileCache[''] || [];
            setSelectedFiles( rootFiles.map( f => f.path ) );
        } else {
            setSelectedFiles( [] );
        }
    };

    const toggleFile = ( path ) => {
        if ( selectedFiles.includes( path ) ) {
            setSelectedFiles( selectedFiles.filter( p => p !== path ) );
        } else {
            setSelectedFiles( [ ...selectedFiles, path ] );
        }
    };

    const handleQuarantine = async ( filesToQuarantine = null ) => {
        const files = filesToQuarantine || selectedFiles;
        if ( ! files.length ) return;
        
        const result = await MySwal.fire({
            title: 'Quarantine Files?',
            text: files.length === 1 
                ? `Move '${files[0]}' to quarantine?` 
                : `Move ${files.length} files to quarantine?`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d63638',
            confirmButtonText: 'Yes, quarantine them!'
        });

        if ( ! result.isConfirmed ) return;

        try {
            const res = await apiFetch( {
                path: '/wp-force-repair/v1/core/quarantine',
                method: 'POST',
                data: { files: files }
            } );
             
            if ( res.success ) {
                showSuccessToast( `Successfully quarantined ${res.moved.length} files.` );
                setSelectedFiles([]);
                refreshCurrentView();
                fetchQuarantined(); 
            }
        } catch ( e ) {
             showErrorAlert( 'Error', e.message );
        }
    };

    const handleRestore = async ( path ) => {
        const result = await MySwal.fire({
            title: 'Restore File?',
            text: `Restore this file to the root directory?\n${path}`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Yes, restore it!'
        });

        if ( ! result.isConfirmed ) return;
        
        try {
            const res = await apiFetch( {
                path: '/wp-force-repair/v1/core/restore',
                method: 'POST',
                data: { path: path }
            } );
            
            if ( res.success ) {
                showSuccessToast( res.message );
                refreshCurrentView();
                fetchQuarantined();
            }
        } catch ( e ) {
            showErrorAlert( 'Error', e.message );
        }
    };

    const handleViewFile = async ( file ) => {
        if( file.type === 'directory' ) return;
        MySwal.fire({ title: 'Loading...', didOpen: () => MySwal.showLoading() });
        try {
            const res = await apiFetch({ path: `/wp-force-repair/v1/core/tools/view-file?file=${file.path}` });
            MySwal.fire({
                title: file.name,
                html: `<pre style="text-align:left; max-height:400px; overflow:auto; background:#f0f0f1; padding:10px; border-radius:4px; font-size: 12px;">${String(res.content || '').replace(/</g, '&lt;').replace(/>/g, '&gt;')}</pre>`,
                width: '800px',
                showConfirmButton: true,
                confirmButtonText: 'Close'
            });
        } catch ( e ) {
            showErrorAlert( 'Error', e.message );
        }
    };

    const handleDeleteQuarantined = async ( path ) => {
        const result = await MySwal.fire({
            title: 'Permanently Delete?',
            text: "You won't be able to revert this!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            confirmButtonText: 'Yes, delete it!'
        });

        if ( ! result.isConfirmed ) return;

        try {
            const res = await apiFetch( {
                path: '/wp-force-repair/v1/core/quarantine/delete',
                method: 'POST', 
                data: { path: path }
            } );

            if ( res.success ) {
                fetchQuarantined(); 
                showSuccessToast( 'File has been deleted.' );
            }
        } catch ( e ) {
             showErrorAlert( 'Error', e.message );
        }
    };

    const deleteFolder = async ( folder ) => {
        const result = await MySwal.fire({
            title: 'Delete Folder?',
            text: "This will remove the empty quarantine folder.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            confirmButtonText: 'Yes, delete it!'
        });

        if ( ! result.isConfirmed ) return;

        try {
            await apiFetch({
                path: '/wp-force-repair/v1/core/quarantine/delete-folder',
                method: 'POST',
                data: { folder: folder }
            });

            showSuccessToast( 'Folder has been removed.' );
            fetchQuarantined(); 
        } catch ( error ) {
            showErrorAlert( 'Error', error.message );
        }
    };

    const handleReinstall = async () => {
        const result = await MySwal.fire({
            title: 'Re-install Core?',
            html: `
                <div style="text-align: left;">
                    <p style="margin-bottom: 10px;">WARNING: This will replace all WordPress Core files.</p>
                    <ul style="list-style: disc; margin-left: 20px; margin-bottom: 20px;">
                        <li><b>wp-admin</b> & <b>wp-includes</b> will be replaced.</li>
                        <li>Root PHP files will be overwritten.</li>
                        <li>Your <b>wp-content</b> and <b>wp-config.php</b> are SAFE.</li>
                    </ul>
                    <div style="background: #fff; padding: 10px; border: 1px solid #ddd; border-left: 4px solid #d63638;">
                        <label style="font-weight: 600; display: flex; align-items: center; gap: 8px; cursor: pointer;">
                            <input type="checkbox" id="swal-quarantine-checkbox" /> 
                            Quarantine unknown files?
                        </label>
                        <p style="margin: 5px 0 0 25px; font-size: 12px; color: #666;">
                            If checked, any non-standard files in root will be moved to quarantine safe storage.
                        </p>
                    </div>
                </div>
            `,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#2271b1',
            confirmButtonText: 'Yes, Re-install Core',
            preConfirm: () => {
                return { quarantine: document.getElementById('swal-quarantine-checkbox').checked };
            }
        });

        if ( ! result.isConfirmed ) return;

        const shouldQuarantine = result.value.quarantine;

        setIsInstalling( true );
        setInstallStatus( 'processing' );
        setInstallLogs( [ 'Initiating Safe Core Reinstall...', shouldQuarantine ? 'Quarantine enabled.' : 'Quarantine disabled.', 'Verifying backup integrity...' ] );

        try {
            const res = await apiFetch( {
                path: '/wp-force-repair/v1/core/reinstall',
                method: 'POST',
                data: { 
                    version: status?.latest_version || 'latest',
                    quarantine_unknowns: shouldQuarantine
                }
            } );

            if ( res.success ) {
                setInstallStatus( 'success' );
                setInstallMessage( 'WordPress Core successfully re-installed.' );
                if ( res.logs ) setInstallLogs( prev => [ ...prev, ...res.logs ] );
                refreshCurrentView();
                fetchQuarantined();
            }
        } catch ( e ) {
            setInstallStatus( 'error' );
            setInstallMessage( e.message || 'Reinstall failed.' );
            if ( e.logs ) setInstallLogs( prev => [ ...prev, ...e.logs ] );
        }
    };

    const closeOverlay = () => {
        setIsInstalling( false );
        setInstallLogs( [] );
        fetchStatus();
        refreshCurrentView();
    };

    const rootFiles = fileCache[''] || [];

    if ( loading ) {
         return <div className="notice notice-info inline" style={{ marginTop: '20px' }}><p>Loading Core Status...</p></div>;
    }

    return (
        <div className="wfr-view-container">
            <InstallerOverlay 
                isOpen={ isInstalling }
                logs={ installLogs }
                status={ installStatus }
                message={ installMessage }
                onClose={ closeOverlay }
                progress={ ""} 
            />

            <div className="wfr-section-header" style={{ marginTop: '10px', marginBottom: '20px' }}>
                <h2 className="title">WordPress Core Manager</h2>
                <p className="description">Scan your core files for integrity, restore modified files, or force a clean re-install of WordPress.</p>
            </div>
            
            <div className="wfr-core-status-card postbox" style={{ padding: '20px', display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
                        <div>
                            <h3 style={{ margin: '0 0 10px 0' }}>Current Version: { status?.version }</h3>
                            <p style={{ margin: 0, color: status?.has_update ? '#d63638' : '#00a32a', fontWeight: 600 }}>
                                { status?.has_update ? 'Update Available (' + status.latest_version + ')' : 'You are on the latest version.' }
                            </p>
                            <p style={{ margin: '5px 0 0 0', color: '#666', fontSize: '13px' }}>Locale: { status?.locale }</p>
                        </div>
                        <div>
                             <button className="button button-primary button-hero" onClick={ handleReinstall }>
                                Force Re-install Core
                             </button>
                             <p className="description" style={{ textAlign: 'center', marginTop: '5px' }}>SAFE: Preserves wp-content & config</p>
                        </div>
                    </div>

                    <div className="wfr-scan-section card" style={{ marginTop: '20px', padding: '15px', maxWidth: '100%' }}>
                        <h3 style={{ marginTop: 0 }}>File Integrity Scan (Root Directory)</h3>
                        <p>The system automatically hides standard WordPress files in the root for clarity.</p>
                        
                        { scanning && rootFiles.length === 0 ? <p>Scanning...</p> : (
                            <>
                                <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: '10px' }}>
                                    <p style={{ margin: 0 }}>Found <strong>{rootFiles.length}</strong> non-standard items in root.</p>
                                    <div style={{ display: 'flex', gap: '10px' }}>
                                        <button className="button button-secondary" disabled={ selectedFiles.length === 0 } onClick={ () => handleQuarantine() }>
                                            Quarantine Selected ({selectedFiles.length})
                                        </button>
                                    </div>
                                </div>
                                
                                <table className="widefat striped">
                                    <thead>
                                        <tr>
                                            <td className="manage-column column-cb check-column">
                                                <input type="checkbox" onChange={ toggleSelectAll } />
                                            </td>
                                            <th>Name</th>
                                            <th>Type</th>
                                            <th>Size</th>
                                            <th>Perms</th>
                                            <th>Modified</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        { rootFiles.map( (file, i) => (
                                            <FileNode 
                                                key={ i }
                                                file={ file }
                                                depth={ 0 }
                                                expandedPaths={ expandedPaths }
                                                fileCache={ fileCache }
                                                selectedFiles={ selectedFiles }
                                                onToggle={ toggleFolder }
                                                onToggleSelection={ toggleFile }
                                                onView={ handleViewFile }
                                                onQuarantine={ handleQuarantine }
                                            />
                                        )) }
                                    </tbody>
                                </table>
                            </>
                        )}
                    </div>
                
                    {/* Quarantine Viewer */}
                    { quarantinedData.length > 0 && (
                        <div className="wfr-quarantine-section card" style={{ marginTop: '20px', padding: '15px', maxWidth: '100%' }}>
                            <h3 style={{ marginTop: 0 }}>Quarantined Files</h3>
                            <p>Files moved here are safe and inactive. You can restore them if needed.</p>
                            
                            { quarantinedData.map( ( q, i ) => (
                                <QuarantineItem 
                                    key={ i } 
                                    data={ q } 
                                    onDeleteFolder={ deleteFolder }
                                    onRestore={ handleRestore }
                                    onDeleteFile={ handleDeleteQuarantined }
                                />
                            ) ) }
                        </div>
                    ) }
        </div>
    );
};

export default CoreManager;
