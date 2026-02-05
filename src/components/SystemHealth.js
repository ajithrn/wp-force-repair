const { useState } = wp.element;
const apiFetch = wp.apiFetch;
import Swal from 'sweetalert2';
import withReactContent from 'sweetalert2-react-content';

const MySwal = withReactContent(Swal);

const SystemHealth = () => {
    return (
        <div className="wfr-system-health-view" style={{ marginTop: '20px' }}>
             <div className="wfr-section-header">
                <h2 className="title">System Health & Repair</h2>
                <p className="description">Use these tools to fix common configuration and 404 errors.</p>
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
                </div>
            </div>
        </div>
    );
};

export default SystemHealth;
