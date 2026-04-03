const { useState, useEffect } = wp.element;
const apiFetch = wp.apiFetch;
import Swal from 'sweetalert2';
import withReactContent from 'sweetalert2-react-content';

const MySwal = withReactContent(Swal);

// Secure server-side download URL (bypasses .htaccess Deny from all)
const getDownloadUrl = ( filename ) => {
    const nonce = wfrSettings.nonce;
    const root  = wfrSettings.root.replace( /\/$/, '' );
    return `${root}/wp-force-repair/v1/backup/download?file=${encodeURIComponent(filename)}&_wpnonce=${nonce}`;
};

const BackupManager = () => {
    const [ caps, setCaps ]                     = useState( null );
    const [ working, setWorking ]               = useState( false );
    const [ backups, setBackups ]               = useState( [] );
    const [ loadingBackups, setLoadingBackups ] = useState( true );

    useEffect( () => {
        apiFetch({ path: '/wp-force-repair/v1/backup/capabilities' })
            .then( setCaps )
            .catch( console.error );
        fetchBackups();
    }, [] );

    const fetchBackups = async () => {
        setLoadingBackups( true );
        try {
            const res = await apiFetch({ path: '/wp-force-repair/v1/backup/list' });
            setBackups( res.backups || [] );
        } catch(e) {
            console.error( e );
        }
        setLoadingBackups( false );
    };

    const deleteBackupFile = async ( filename ) => {
        const result = await MySwal.fire({
            title: 'Delete Backup?',
            text: `Remove "${filename}" from the server? This cannot be undone.`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d63638',
            confirmButtonText: 'Delete',
            cancelButtonText: 'Cancel',
        });
        if ( ! result.isConfirmed ) return;
        try {
            await apiFetch({
                path: '/wp-force-repair/v1/backup/delete',
                method: 'POST',
                data: { file: filename }
            });
            MySwal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Backup deleted', showConfirmButton: false, timer: 2000 });
            fetchBackups();
        } catch(e) {
            MySwal.fire( 'Error', e.message, 'error' );
        }
    };

    const handleBackup = async ( type ) => {
        let excludeMedia = false;

        if ( type === 'files' ) {
            const { value: formValues } = await MySwal.fire({
                title: 'Create File Backup',
                html: `
                    <p style="text-align:left; margin:0 0 15px; color:#3c434a;">This will compress your entire WordPress installation into a ZIP archive.</p>
                    <div style="text-align:left; background:#f6f7f7; padding:15px; border-radius:4px; border:1px solid #c3c4c7;">
                        <label style="display:flex; align-items:flex-start; gap:12px; cursor:pointer;">
                            <input type="checkbox" id="exclude_media" style="margin-top:3px; accent-color:#2271b1; width:16px; height:16px; flex-shrink:0;" />
                            <div>
                                <strong style="display:block; font-size:13px; color:#1d2327;">Exclude Media Library</strong>
                                <span style="font-size:12px; color:#646970; margin-top:2px; display:block;">Recommended — skipping uploads significantly reduces file size and processing time.</span>
                            </div>
                        </label>
                    </div>
                    <div style="margin-top:12px; padding:10px 12px; background:#fff8e5; border-left:3px solid #dba617; text-align:left; font-size:12px; color:#646970; border-radius:0 3px 3px 0;">
                        Processing time depends on your total site size. Large sites may take several minutes. Do not close this window during the process.
                    </div>
                `,
                showCancelButton: true,
                confirmButtonText: 'Start Backup',
                cancelButtonText: 'Cancel',
                confirmButtonColor: '#2271b1',
                preConfirm: () => [ document.getElementById('exclude_media').checked ]
            });

            if ( ! formValues ) return;
            excludeMedia = formValues[0];
        } else {
            const result = await MySwal.fire({
                title: 'Backup Database',
                text: 'This will export your entire database to a compressed SQL file.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Start Backup',
                cancelButtonText: 'Cancel',
                confirmButtonColor: '#2271b1',
            });
            if ( ! result.isConfirmed ) return;
        }

        setWorking( true );

        MySwal.fire({
            title: type === 'db' ? 'Backing up database...' : 'Backing up files...',
            html: `
                <p style="color:#3c434a;">
                    ${ type === 'db' ? 'Exporting database tables to SQL.' : 'Compressing WordPress files into a ZIP archive.' }
                </p>
                <p style="font-size:12px; color:#646970; margin-top:8px;">Do not close or refresh this window.</p>
            `,
            didOpen: () => MySwal.showLoading(),
            allowOutsideClick: false
        });

        try {
            const res = await apiFetch({
                path: '/wp-force-repair/v1/backup/create',
                method: 'POST',
                data: { type, exclude_media: excludeMedia }
            });

            if ( res.success ) {
                const downloadUrl = getDownloadUrl( res.file );

                const dlResult = await MySwal.fire({
                    title: 'Backup Ready',
                    html: `
                        <p style="color:#3c434a; margin-bottom:15px;">Your backup has been created successfully.</p>
                        <a
                            href="${downloadUrl}"
                            class="button button-primary"
                            style="display:inline-block; margin-bottom:16px; padding:8px 18px; text-decoration:none;"
                            download
                        >Download Backup</a>
                        <div style="background:#f6f7f7; border:1px solid #c3c4c7; border-radius:4px; padding:10px 12px; text-align:left;">
                            <div style="font-size:11px; color:#646970; margin-bottom:4px;">Filename</div>
                            <div style="font-size:12px; font-family:monospace; color:#1d2327; word-break:break-all;">${res.file}</div>
                        </div>
                        <p style="margin-top:12px; font-size:12px; color:#d63638; text-align:left;">
                            For security, delete this file from the server once you have downloaded it.
                        </p>
                    `,
                    icon: 'success',
                    showCancelButton: true,
                    confirmButtonText: 'Delete from Server',
                    cancelButtonText: 'Keep on Server',
                    confirmButtonColor: '#d63638',
                    cancelButtonColor: '#787c82',
                });

                if ( dlResult.isConfirmed ) {
                    await apiFetch({
                        path: '/wp-force-repair/v1/backup/delete',
                        method: 'POST',
                        data: { file: res.file }
                    });
                    MySwal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Backup deleted from server', showConfirmButton: false, timer: 2500 });
                } else {
                    MySwal.fire({ toast: true, position: 'top-end', icon: 'info', title: 'Backup retained on server', showConfirmButton: false, timer: 2500 });
                }

                fetchBackups();
            }
        } catch(e) {
            MySwal.fire( 'Backup Failed', e.message || 'An unexpected error occurred. Check server logs.', 'error' );
        }

        setWorking( false );
    };

    return (
        <div className="wfr-backup-manager-view" style={{ marginTop: '20px' }}>

            {/* Page Header */}
            <div style={{ marginBottom: '16px', display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start' }}>
                <div>
                    <h2 className="title" style={{ marginBottom: '4px' }}>Backup Manager</h2>
                    <p className="description" style={{ margin: 0 }}>Create and manage backups of your WordPress database and files.</p>
                </div>
                <button
                    className="button button-secondary"
                    onClick={ fetchBackups }
                    disabled={ loadingBackups }
                    style={{ flexShrink: 0, marginTop: '4px' }}
                >
                    <span className="dashicons dashicons-update" style={{ marginTop: '3px' }}></span> Refresh
                </button>
            </div>

            {/* Security Notice */}
            <div className="notice notice-warning inline" style={{ marginBottom: '20px', borderLeft: '4px solid #dba617' }}>
                <p style={{ margin: 0 }}>
                    <strong>Security:</strong> Backup files are protected from direct URL access and can only be downloaded via this interface. Delete backups from the server once you have saved a local copy.
                </p>
            </div>

            {/* Create New Backup — full width horizontal card */}
            <div style={{ padding: '20px', marginBottom: '20px', background: '#fff', border: '1px solid #c3c4c7', borderRadius: '4px', boxShadow: '0 1px 1px rgba(0,0,0,.04)' }}>
                <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', gap: '40px', flexWrap: 'wrap' }}>

                    {/* Left: Description + Capabilities */}
                    <div style={{ flex: '1 1 400px', minWidth: 0 }}>
                        <h3 style={{ marginTop: 0, marginBottom: '6px', fontSize: '14px' }}>Create New Backup</h3>
                        <p style={{ color: '#646970', fontSize: '13px', margin: '0 0 16px' }}>
                            Generate a fresh backup before performing repairs, updates, or database changes.
                        </p>

                        {/* Capabilities — horizontal pill row */}
                        <div style={{ display: 'flex', flexWrap: 'wrap', gap: '8px', fontSize: '12px' }}>
                            { caps ? (
                                <>
                                    <span style={{
                                        display: 'inline-flex', alignItems: 'center', gap: '6px',
                                        background: caps.zip_archive ? '#e7f3ec' : '#fcecec',
                                        color: caps.zip_archive ? '#00a32a' : '#d63638',
                                        padding: '3px 10px', borderRadius: '20px', fontWeight: 500
                                    }}>
                                        <span style={{ width: '6px', height: '6px', borderRadius: '50%', background: 'currentColor', display: 'inline-block' }}></span>
                                        { caps.zip_archive ? 'ZipArchive available' : 'ZipArchive missing' }
                                    </span>
                                    <span style={{
                                        display: 'inline-flex', alignItems: 'center', gap: '6px',
                                        background: caps.shell_exec ? '#e7f3ec' : '#fef8ec',
                                        color: caps.shell_exec ? '#00a32a' : '#dba617',
                                        padding: '3px 10px', borderRadius: '20px', fontWeight: 500
                                    }}>
                                        <span style={{ width: '6px', height: '6px', borderRadius: '50%', background: 'currentColor', display: 'inline-block' }}></span>
                                        { caps.shell_exec ? 'Fast DB dump (shell)' : 'PHP fallback (slow)' }
                                    </span>
                                    <span style={{
                                        display: 'inline-flex', alignItems: 'center', gap: '6px',
                                        background: '#f0f0f1', color: '#50575e',
                                        padding: '3px 10px', borderRadius: '20px', fontWeight: 500
                                    }}>
                                        Memory: { caps.memory_limit }
                                    </span>
                                </>
                            ) : (
                                <span style={{ color: '#646970' }}>Checking server capabilities...</span>
                            )}
                        </div>
                    </div>

                    {/* Right: Action buttons */}
                    <div style={{ display: 'flex', gap: '10px', alignItems: 'center', flexShrink: 0, flexWrap: 'wrap' }}>
                        <button
                            className="button button-primary button-hero"
                            disabled={ working }
                            onClick={ () => handleBackup('db') }
                            style={{ minWidth: '200px' }}
                        >
                            { working ? 'Working...' : 'Backup Database (SQL)' }
                        </button>
                        <button
                            className="button button-secondary button-hero"
                            disabled={ working || ! caps?.zip_archive }
                            onClick={ () => handleBackup('files') }
                            style={{ minWidth: '180px' }}
                            title={ ! caps?.zip_archive ? 'ZipArchive PHP extension is required' : '' }
                        >
                            { working ? 'Working...' : 'Backup Files (ZIP)' }
                        </button>
                    </div>

                </div>
            </div>

            {/* Stored Backups — full width */}
            <div style={{ padding: '0', overflow: 'hidden', background: '#fff', border: '1px solid #c3c4c7', borderRadius: '4px', boxShadow: '0 1px 1px rgba(0,0,0,.04)' }}>
                <div style={{ padding: '14px 20px', background: '#f6f7f7', borderBottom: '1px solid #c3c4c7', display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                    <h3 style={{ margin: 0, fontSize: '14px', fontWeight: 600 }}>
                        Stored Backups
                        { ! loadingBackups && backups.length > 0 && (
                            <span style={{
                                marginLeft: '8px',
                                background: '#2271b1',
                                color: '#fff',
                                borderRadius: '10px',
                                padding: '1px 8px',
                                fontSize: '11px',
                                fontWeight: 600,
                                verticalAlign: 'middle'
                            }}>{ backups.length }</span>
                        )}
                    </h3>
                </div>

                { loadingBackups ? (
                    <div style={{ padding: '30px 20px', color: '#646970', fontSize: '13px' }}>
                        Loading backups...
                    </div>
                ) : backups.length === 0 ? (
                    <div style={{ padding: '50px 20px', textAlign: 'center', color: '#646970' }}>
                        <span className="dashicons dashicons-archive" style={{ fontSize: '40px', width: '40px', height: '40px', color: '#c3c4c7', display: 'block', margin: '0 auto 12px' }}></span>
                        <p style={{ margin: '0 0 6px', fontSize: '14px', fontWeight: 500 }}>No backups stored on the server</p>
                        <p style={{ margin: 0, fontSize: '12px', color: '#999' }}>Use the controls above to create your first backup.</p>
                    </div>
                ) : (
                    <table className="widefat" style={{ border: 'none', width: '100%' }}>
                        <thead>
                            <tr>
                                <th style={{ paddingLeft: '20px' }}>Filename</th>
                                <th style={{ width: '100px', textAlign: 'center' }}>Type</th>
                                <th style={{ width: '90px', textAlign: 'center' }}>Size</th>
                                <th style={{ width: '160px', textAlign: 'center' }}>Created</th>
                                <th style={{ width: '140px', textAlign: 'right', paddingRight: '20px' }}>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            { backups.map( ( b, i ) => {
                                const isDb      = b.file.includes('-db-');
                                const typeLabel = isDb ? 'Database' : 'Files';
                                const typeBg    = isDb ? '#e7f3ec' : '#e8f0fb';
                                const typeColor = isDb ? '#00a32a' : '#2271b1';

                                return (
                                    <tr key={ i } style={{ borderBottom: '1px solid #f0f0f0' }}>
                                        <td style={{ paddingLeft: '20px', paddingTop: '12px', paddingBottom: '12px' }}>
                                            <span style={{ fontFamily: 'monospace', fontSize: '12px', color: '#1d2327', wordBreak: 'break-all' }}>
                                                { b.file }
                                            </span>
                                        </td>
                                        <td style={{ textAlign: 'center' }}>
                                            <span style={{
                                                display: 'inline-block',
                                                background: typeBg,
                                                color: typeColor,
                                                fontSize: '11px',
                                                fontWeight: 600,
                                                padding: '2px 10px',
                                                borderRadius: '20px',
                                            }}>{ typeLabel }</span>
                                        </td>
                                        <td style={{ textAlign: 'center', color: '#646970', fontSize: '13px', whiteSpace: 'nowrap' }}>
                                            { b.size }
                                        </td>
                                        <td style={{ textAlign: 'center', color: '#646970', fontSize: '12px', whiteSpace: 'nowrap' }}>
                                            { b.date }
                                        </td>
                                        <td style={{ textAlign: 'right', paddingRight: '20px', whiteSpace: 'nowrap' }}>
                                            <a
                                                href={ getDownloadUrl( b.file ) }
                                                className="button button-small button-primary"
                                                style={{ marginRight: '6px', textDecoration: 'none' }}
                                                download
                                            >
                                                Download
                                            </a>
                                            <button
                                                className="button button-small button-link-delete"
                                                onClick={ () => deleteBackupFile( b.file ) }
                                            >
                                                Delete
                                            </button>
                                        </td>
                                    </tr>
                                );
                            }) }
                        </tbody>
                    </table>
                )}
            </div>

            <p style={{ marginTop: '12px', fontSize: '12px', color: '#999', textAlign: 'right' }}>
                Stored in <code>/wp-content/uploads/wfr-backups/</code> — protected from public access via .htaccess
            </p>
        </div>
    );
};

export default BackupManager;
