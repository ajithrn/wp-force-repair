const { useState, useEffect } = wp.element;
const apiFetch = wp.apiFetch;
import Swal from 'sweetalert2';
import withReactContent from 'sweetalert2-react-content';

const MySwal = withReactContent(Swal);

const SystemHealth = () => {
    const [ commentStats, setCommentStats ] = useState( null );
    const [ loadingStats, setLoadingStats ] = useState( true );

    useEffect( () => {
        fetchStats();
    }, [] );

    const fetchStats = async () => {
        try {
            const stats = await apiFetch({ path: '/wp-force-repair/v1/core/tools/comment-stats' });
            setCommentStats( stats );
        } catch(e) {
            console.error(e);
        }
        setLoadingStats( false );
    };

    const handleCleanup = async ( type, label ) => {
        const count = commentStats[type];
        if ( count == 0 ) return;

        const result = await MySwal.fire({
            title: `Delete ${label}?`,
            text: `Permanently delete ${count} ${label.toLowerCase()}?`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d63638',
            confirmButtonText: 'Yes, delete all'
        });

        if ( result.isConfirmed ) {
            MySwal.fire({ title: 'Cleaning...', didOpen: () => MySwal.showLoading() });
            
            try {
                const res = await apiFetch({ 
                    path: '/wp-force-repair/v1/core/tools/cleanup-comments', 
                    method: 'POST',
                    data: { type: type }
                });
                
                await fetchStats(); // Refresh stats
                
                MySwal.fire( 'Cleaned!', res.message, 'success' );
            } catch(e) {
                MySwal.fire( 'Error', e.message, 'error' );
            }
        }
    };

    return (
        <div className="wfr-system-health-view" style={{ marginTop: '20px' }}>
             <div className="wfr-section-header" style={{ marginBottom: '20px' }}>
                <h2 className="title">System Tools</h2>
                <p className="description">Use these tools to fix common configuration errors, perform maintenance, and manage backups.</p>
            </div>

             <div className="wfr-system-tools-card card" style={{ marginTop: '20px', padding: '20px', maxWidth: '100%' }}>
                <h3 style={{ marginTop: 0 }}>Comment Cleanup</h3>
                <p>Bulk delete spam, trash, or pending comments to clean your database.</p>
                
                { loadingStats ? <p>Loading stats...</p> : (
                    <div style={{ display: 'flex', gap: '15px', marginTop: '15px' }}>
                        <button className="button button-secondary" disabled={ commentStats?.spam == 0 } onClick={ () => handleCleanup('spam', 'Spam Comments') }>
                            Delete Spam ({ commentStats?.spam || 0 })
                        </button>
                        <button className="button button-secondary" disabled={ commentStats?.trash == 0 } onClick={ () => handleCleanup('trash', 'Trash Comments') }>
                            Empty Trash ({ commentStats?.trash || 0 })
                        </button>
                        <button className="button button-secondary" disabled={ commentStats?.moderated == 0 } onClick={ () => handleCleanup('moderated', 'Pending Comments') }>
                            Delete Pending ({ commentStats?.moderated || 0 })
                        </button>
                    </div>
                )}
            </div>

            <div className="wfr-system-tools-card card" style={{ marginTop: '20px', padding: '20px', maxWidth: '100%' }}>
                <h3 style={{ marginTop: 0 }}>Permalinks & Rewrites</h3>
                <p>Fixes issues where pages return "404 Not Found" or URL structures are broken.</p>
                <div style={{ display: 'flex', gap: '15px', marginTop: '15px' }}>
                    <button className="button button-secondary button-hero" onClick={ async () => {
                         const result = await MySwal.fire({
                            title: 'Flush Permalinks?',
                            text: 'This will reset your rewrite rules. Useful for fixing 404 errors.',
                            icon: 'question',
                            showCancelButton: true,
                            confirmButtonText: 'Yes, flush them'
                        });
                        
                        if( result.isConfirmed ) {
                            try {
                                const res = await apiFetch({ path: '/wp-force-repair/v1/core/tools/flush-permalinks', method: 'POST' });
                                MySwal.fire( 'Success', res.message, 'success' );
                            } catch(e) { MySwal.fire( 'Error', e.message, 'error' ); }
                        }
                    }}>
                        Flush Permalinks
                    </button>
                    
                     <button className="button button-secondary button-hero" onClick={ async () => {
                        const result = await MySwal.fire({
                            title: 'Regenerate .htaccess?',
                            text: 'A backup of your current .htaccess will be created before regeneration.',
                            icon: 'warning',
                            showCancelButton: true,
                            confirmButtonText: 'Yes, regenerate'
                        });
                        
                        if( result.isConfirmed ) {
                            try {
                                const res = await apiFetch({ path: '/wp-force-repair/v1/core/tools/regenerate-htaccess', method: 'POST' });
                                MySwal.fire( 'Success', res.message + ( res.backup ? '\nBackup: ' + res.backup : '' ), 'success' );
                            } catch(e) { MySwal.fire( 'Error', e.message, 'error' ); }
                        }
                    }}>
                        Regenerate .htaccess
                    </button>
                </div>
            </div>

            <div className="wfr-system-tools-card card" style={{ marginTop: '20px', padding: '20px', maxWidth: '100%' }}>
                <h3 style={{ marginTop: 0 }}>Security Tools</h3>
                <p>Advanced security actions. Use with caution.</p>
                <div style={{ display: 'flex', gap: '15px', marginTop: '15px' }}>
                    <button className="button button-secondary button-hero" onClick={ async () => {
                        const result = await MySwal.fire({
                            title: 'Regenerate Salt Keys?',
                            html: "This will fetch new security keys from WordPress.org and update your wp-config.php.<br/><br/><strong style='color:#d63638'>YOU WILL BE LOGGED OUT IMMEDIATELY.</strong>",
                            icon: 'warning',
                            showCancelButton: true,
                            confirmButtonColor: '#d63638',
                            confirmButtonText: 'Yes, Regenerate & Logout'
                        });
                        
                        if( result.isConfirmed ) {
                            try {
                                const res = await apiFetch({ path: '/wp-force-repair/v1/core/tools/regenerate-salts', method: 'POST' });
                                await MySwal.fire( 'Success', res.message, 'success' );
                                window.location.reload(); // Reloading usually forces the logout redirect
                            } catch(e) { MySwal.fire( 'Error', e.message, 'error' ); }
                        }
                    }}>
                        Regenerate Salt Keys
                    </button>
                    
                    <button className="button button-secondary button-hero" onClick={ async () => {
                        const result = await MySwal.fire({
                            title: 'Reset File Permissions?',
                            html: "This will recursively set:<br/>Folders -> 0755<br/>Files -> 0644<br/><br/>This process may take some time.",
                            icon: 'warning',
                            showCancelButton: true,
                            confirmButtonText: 'Yes, Reset Permissions'
                        });
                        
                        if( result.isConfirmed ) {
                            MySwal.fire({ title: 'Reseting Permissions...', didOpen: () => MySwal.showLoading() });
                            try {
                                const res = await apiFetch({ path: '/wp-force-repair/v1/core/tools/reset-permissions', method: 'POST' });
                                MySwal.fire( 'Success', res.message, 'success' );
                            } catch(e) { MySwal.fire( 'Error', e.message, 'error' ); }
                        }
                    }}>
                        Reset File Permissions
                    </button>
                </div>
            </div>

            <BackupManager />
        </div>
    );
};

const BackupManager = () => {
    const [ caps, setCaps ] = useState(null);
    const [ working, setWorking ] = useState(false);

    useEffect( () => {
        apiFetch({ path: '/wp-force-repair/v1/backup/capabilities' })
            .then( setCaps )
            .catch( console.error );
    }, [] );

    const handleBackup = async ( type ) => {
        setWorking(true);
        
        const title = type === 'db' ? 'Backing up Database...' : 'Backing up Files...';
        const message = type === 'db' 
            ? 'Exporting database tables and securing data...' 
            : 'Compressing site files into a ZIP archive...';

        MySwal.fire({
            title: title,
            html: `<div style="margin-top:10px; font-weight:500;">${message}</div><div style="margin-top:5px; font-size:12px; color:#666">Please do not close this window.</div>`,
            didOpen: () => MySwal.showLoading(),
            allowOutsideClick: false
        });

        try {
            const res = await apiFetch({ 
                path: '/wp-force-repair/v1/backup/create', 
                method: 'POST',
                data: { type: type }
            });
            
            if ( res.success ) {
                await MySwal.fire({
                    title: 'Backup Ready!',
                    html: `
                        <p>Your backup is ready for download.</p>
                        <a href="${res.url}" class="button button-primary button-hero" target="_blank" download>Download Backup</a>
                        <p style="margin-top: 15px; font-size: 12px; color: #666;">
                            File: ${res.file}<br/>
                            <strong style="color: #d63638">Important:</strong> Please delete this file after downloading to save space and security.
                        </p>
                    `,
                    icon: 'success',
                    showCancelButton: true,
                    cancelButtonText: 'Delete Backup Now',
                    confirmButtonText: 'I have downloaded it'
                }).then( (result) => {
                    if ( result.dismiss === Swal.DismissReason.cancel || result.isConfirmed ) {
                        // Cleanup
                         apiFetch({ 
                            path: '/wp-force-repair/v1/backup/delete', 
                            method: 'POST',
                            data: { file: res.file }
                        });
                        if ( result.dismiss === Swal.DismissReason.cancel ) {
                            MySwal.fire('Deleted', 'Backup file removed from server.', 'success');
                        }
                    }
                });
            }
        } catch(e) {
            MySwal.fire( 'Backup Failed', e.message || 'Unknown Error', 'error' );
        }
        setWorking(false);
    };

    return (
        <div className="wfr-system-tools-card card" style={{ marginTop: '20px', padding: '20px', maxWidth: '100%' }}>
            <h3 style={{ marginTop: 0 }}>Backup Manager (Beta)</h3>
            <p>Download a full backup of your site or database before making repairs.</p>
            
            <div style={{ marginTop: '10px', marginBottom: '15px' }}>
                { caps ? (
                    <div style={{ fontSize: '12px', display: 'flex', gap: '15px' }}>
                        <span style={{ color: caps.zip_archive ? 'green' : 'red' }}>
                             { caps.zip_archive ? '✔ ZipArchive Supported' : '✖ ZipArchive Missing' }
                        </span>
                        <span style={{ color: caps.shell_exec ? 'green' : 'orange' }}>
                             { caps.shell_exec ? '✔ Fast DB Dump (Shell)' : '⚠ Slow DB Dump (PHP Fallback)' }
                        </span>
                    </div>
                ) : 'Checking server capabilities...' }
            </div>

            <div style={{ display: 'flex', gap: '15px', marginTop: '15px' }}>
                 <button 
                    className="button button-secondary button-hero" 
                    disabled={ working || ! caps?.zip_archive } 
                    onClick={ () => handleBackup('files') }
                >
                    Download Files (ZIP)
                </button>
                <button 
                    className="button button-secondary button-hero" 
                    disabled={ working } 
                    onClick={ () => handleBackup('db') }
                >
                    Download Database (SQL)
                </button>
            </div>
        </div>
    );
};


export default SystemHealth;
