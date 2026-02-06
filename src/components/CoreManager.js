const { useState, useEffect } = wp.element;
const apiFetch = wp.apiFetch;
import InstallerOverlay from './InstallerOverlay';
import Swal from 'sweetalert2';
import withReactContent from 'sweetalert2-react-content';

const MySwal = withReactContent(Swal);

const CoreManager = () => {
    // ... state ...
    const [ status, setStatus ] = useState( null );
    const [ loading, setLoading ] = useState( true );
    const [ scanning, setScanning ] = useState( false );
    const [ suspectedFiles, setSuspectedFiles ] = useState( [] );
    const [ selectedFiles, setSelectedFiles ] = useState( [] );
    const [ quarantinedData, setQuarantinedData ] = useState( [] );
    
    // Installer Overlay State
    const [ isInstalling, setIsInstalling ] = useState( false );
    const [ installLogs, setInstallLogs ] = useState( [] );
    const [ installStatus, setInstallStatus ] = useState( 'processing' ); 
    const [ installMessage, setInstallMessage ] = useState( '' );

    useEffect( () => {
        fetchStatus();
        scanFiles();
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

    const scanFiles = async () => {
        setScanning( true );
        try {
            const data = await apiFetch( { path: '/wp-force-repair/v1/core/scan', method: 'POST' } );
            setSuspectedFiles( data.suspected_files || [] );
        } catch ( e ) {
            console.error( e );
        }
        setScanning( false );
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
        if ( e.target.checked ) {
            setSelectedFiles( suspectedFiles.map( f => f.name ) );
        } else {
            setSelectedFiles( [] );
        }
    };

    const toggleFile = ( name ) => {
        if ( selectedFiles.includes( name ) ) {
            setSelectedFiles( selectedFiles.filter( f => f !== name ) );
        } else {
            setSelectedFiles( [ ...selectedFiles, name ] );
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
                MySwal.fire( 'Quarantined!', `Successfully quarantined ${res.moved.length} files.`, 'success' );
                setSelectedFiles([]);
                scanFiles(); 
                fetchQuarantined(); 
            }
        } catch ( e ) {
             MySwal.fire( 'Error', e.message, 'error' );
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
                MySwal.fire( 'Restored!', res.message, 'success' );
                scanFiles();
                fetchQuarantined();
            }
        } catch ( e ) {
            MySwal.fire( 'Error', e.message, 'error' );
        }
    };

    const handleViewFile = async ( file ) => {
        if( file.type === 'directory' ) return;
        MySwal.fire({ title: 'Loading...', didOpen: () => MySwal.showLoading() });
        try {
            const res = await apiFetch({ path: `/wp-force-repair/v1/core/tools/view-file?file=${file.name}` });
            MySwal.fire({
                title: file.name,
                html: `<pre style="text-align:left; max-height:400px; overflow:auto; background:#f0f0f1; padding:10px; border-radius:4px; font-size: 12px;">${String(res.content || '').replace(/</g, '&lt;').replace(/>/g, '&gt;')}</pre>`,
                width: '800px',
                showConfirmButton: true,
                confirmButtonText: 'Close'
            });
        } catch(e) {
            MySwal.fire( 'Error', e.message, 'error' );
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
                MySwal.fire( 'Deleted!', 'File has been deleted.', 'success' );
            }
        } catch ( e ) {
             MySwal.fire( 'Error', e.message, 'error' );
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
                scanFiles();
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
        scanFiles();
    };

    if ( loading ) return <div className="wfr-loading">Loading Core Status...</div>;

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

            <div className="wfr-section-header" style={{ marginTop: '10px' }}>
                <h2 className="title">WordPress Core Manager</h2>
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
                <p>The following files were found in your root directory but are <strong>not</strong> standard WordPress files.</p>
                
                { scanning ? <p>Scanning...</p> : (
                    <>
                        <h3>Integrity Scan (Root Directory)</h3>
                        { suspectedFiles.length === 0 ? (
                             <p style={{ color: 'green' }}>✔ No unknown files found in root directory.</p>
                        ) : (
                            <div>
                                <p>Found <strong>{suspectedFiles.length}</strong> unknown files/folders in root.</p>
                                <table className="widefat striped">
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>Type</th>
                                            <th>Size</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        { suspectedFiles.map( (file, i) => (
                                            <tr key={i}>
                                                <td>{file.name}</td>
                                                <td>{file.type}</td>
                                                <td>{file.size}</td>
                                                <td>
                                                    <div style={{ display: 'flex', gap: '5px' }}>
                                                        { file.type !== 'directory' && (
                                                            <button 
                                                                className="button button-small"
                                                                title="View Content"
                                                                onClick={ () => handleViewFile(file) }
                                                            >
                                                                View Content
                                                            </button>
                                                        )}
                                                        <button 
                                                            className="button button-small" 
                                                            onClick={ () => handleQuarantine([file.name]) }
                                                            title="Quarantine"
                                                        >
                                                            Quarantine
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        ) ) }
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </>
                )}
            </div>
        
            {/* Quarantine Viewer */}
            { quarantinedData.length > 0 && (
                <div className="wfr-quarantine-section card" style={{ marginTop: '20px', padding: '15px', maxWidth: '100%' }}>
                    <h3 style={{ marginTop: 0 }}>Quarantined Files</h3>
                    <p>Files moved here are safe and inactive. You can restore them if needed.</p>
                    
                    { quarantinedData.map( ( q, i ) => (
                        <div key={ i } style={{ marginBottom: '15px' }}>
                            <h4 style={{ margin: '0 0 5px 0', borderBottom: '1px solid #eee', paddingBottom: '5px' }}>
                                📁 Backup: { q.timestamp }
                            </h4>
                            <table className="wp-list-table widefat fixed striped" style={{ marginTop: '5px' }}>
                                <tbody>
                                    { q.files.map( ( file, j ) => (
                                        <tr key={ j }>
                                            <td>{ file.name }</td>
                                            <td>{ file.size }</td>
                                            <td style={{ textAlign: 'right' }}>
                                                <button className="button button-small" onClick={ () => handleRestore( file.path ) } style={{ marginRight: '5px' }}>Restore</button>
                                                <button className="button button-small button-link-delete" onClick={ () => handleDeleteQuarantined( file.path ) } style={{ color: '#a00' }}>Delete Permanently</button>
                                            </td>
                                        </tr>
                                    ) ) }
                                </tbody>
                            </table>
                        </div>
                    ) ) }
                </div>
            ) }
        </div>
    );
};

export default CoreManager;
