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
                <p className="description">Use these tools to fix common configuration errors, perform maintenance, and debug system connectivity.</p>
            </div>

            <div className="wfr-system-tools-grid" style={{ 
                display: 'grid', 
                gridTemplateColumns: 'repeat(auto-fit, minmax(400px, 1fr))',
                gap: '20px',
                marginTop: '20px' 
            }}>
                {/* Responsive Hack: Force 2 columns on large screens */}
                <style>{`
                    @media (min-width: 1000px) {
                        .wfr-system-tools-grid { grid-template-columns: 1fr 1fr !important; }
                    }
                `}</style>
                <div className="wfr-system-tools-card card" style={{ margin: 0, padding: '20px', maxWidth: '100%' }}>
                    <h3 style={{ marginTop: 0 }}>Comment Cleanup</h3>
                    <p>Bulk delete spam, trash, or pending comments to clean your database.</p>
                    
                    { loadingStats ? <p>Loading stats...</p> : (
                        <div style={{ display: 'flex', gap: '15px', marginTop: '15px', flexWrap: 'wrap' }}>
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

                <div className="wfr-system-tools-card card" style={{ margin: 0, padding: '20px', maxWidth: '100%' }}>
                    <h3 style={{ marginTop: 0 }}>Permalinks &amp; Rewrites</h3>
                    <p>Fixes issues where pages return "404 Not Found" or URL structures are broken.</p>
                    <div style={{ display: 'flex', gap: '15px', marginTop: '15px', flexWrap: 'wrap' }}>
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

                <div className="wfr-system-tools-card card" style={{ margin: 0, padding: '20px', maxWidth: '100%' }}>
                    <h3 style={{ marginTop: 0 }}>Security Tools</h3>
                    <p>Advanced security actions. Use with caution.</p>
                    <div style={{ display: 'flex', gap: '15px', marginTop: '15px', flexWrap: 'wrap' }}>
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
                                    window.location.reload();
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

                <ConnectivityChecker />
            </div>
        </div>
    );
};

const ConnectivityChecker = () => {
    const [ status, setStatus ] = useState( null );
    const [ checking, setChecking ] = useState( false );

    const checkConnectivity = async () => {
        setChecking( true );
        setStatus( null );
        
        const results = {
            ajax: { status: 'pending', code: 0 },
            rest: { status: 'pending', code: 0 },
            loopback: { status: 'pending', code: 0, message: '' }
        };

        // 1. Check Admin AJAX (Frontend)
        try {
            const res = await fetch( wfrSettings.ajaxUrl + '?action=heartbeat', { method: 'POST' } );
            results.ajax.code = res.status;
            results.ajax.status = res.status === 403 ? 'blocked' : 'ok';
        } catch (e) {
            results.ajax.status = 'error';
        }

        // 2. Check REST API
        try {
            await apiFetch({ path: '/' });
            results.rest.code = 200;
            results.rest.status = 'ok';
        } catch (e) {
            results.rest.code = e.code || 500;
            results.rest.status = 'error';
        }

        // 3. Check Backend Loopback
        try {
            const res = await apiFetch({ path: '/wp-force-repair/v1/core/tools/check-loopback', method: 'POST' });
            results.loopback.code = res.code;
            results.loopback.status = res.status === 'ok' ? 'ok' : 'error';
            results.loopback.message = res.message;
        } catch (e) {
            results.loopback.status = 'error';
            results.loopback.message = e.message;
        }

        setStatus( results );
        setChecking( false );
    };

    return (
        <div className="wfr-system-tools-card card" style={{ margin: 0, padding: '20px', maxWidth: '100%' }}>
            <h3 style={{ marginTop: 0 }}>Connectivity Debugger</h3>
            <p>Diagnose server communication issues. Checks if your server can perform loopback requests (essential for WP-Cron) and if Admin AJAX/REST APIs are accessible.</p>
            
            <button className="button button-secondary" disabled={ checking } onClick={ checkConnectivity }>
                { checking ? 'Testing...' : 'Test Connectivity' }
            </button>

            { status && (
                <div style={{ marginTop: '15px', display: 'grid', gap: '10px' }}>
                    <div style={{ display: 'flex', alignItems: 'center', gap: '10px' }}>
                        <span className={`dashicons dashicons-${status.ajax.status === 'blocked' ? 'no' : 'yes'}`} style={{ color: status.ajax.status === 'blocked' ? '#d63638' : 'green' }}></span>
                        <strong>Frontend AJAX:</strong> 
                        <span>{ status.ajax.status === 'blocked' ? 'BLOCKED (403 Forbidden)' : `Accessible (Code: ${status.ajax.code})` }</span>
                    </div>
                    <div style={{ display: 'flex', alignItems: 'center', gap: '10px' }}>
                        <span className={`dashicons dashicons-${status.rest.status === 'error' ? 'no' : 'yes'}`} style={{ color: status.rest.status === 'error' ? '#d63638' : 'green' }}></span>
                        <strong>REST API:</strong> 
                        <span>{ status.rest.status === 'error' ? `Error (${status.rest.code})` : 'Accessible' }</span>
                    </div>
                    <div style={{ display: 'flex', alignItems: 'center', gap: '10px' }}>
                        <span className={`dashicons dashicons-${status.loopback.status === 'error' ? 'no' : 'yes'}`} style={{ color: status.loopback.status === 'error' ? '#d63638' : 'green' }}></span>
                        <strong>Loopback Request:</strong> 
                        <span>{ status.loopback.status === 'error' ? `Failed (${status.loopback.code})` : 'Working' }</span>
                    </div>
                    
                    { (status.ajax.status === 'blocked' || status.loopback.status === 'error') && (
                        <div style={{ marginTop: '10px', color: '#d63638', fontSize: '13px', background: '#fcecec', padding: '10px', borderLeft: '4px solid #d63638' }}>
                            <strong>Analysis:</strong><br/>
                            { status.ajax.status === 'blocked' && <div>• <strong>Browser 403:</strong> Your security plugin or host WAF is blocking AJAX requests.</div> }
                            { status.loopback.status === 'error' && <div>• <strong>Loopback Failed:</strong> The server cannot connect to itself. This breaks cron jobs and scheduled actions. Check Host/DNS.</div> }
                        </div>
                    )}
                </div>
            )}
        </div>
    );
};

export default SystemHealth;
