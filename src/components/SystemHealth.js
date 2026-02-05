const { useState } = wp.element;
const apiFetch = wp.apiFetch;

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
                        if( confirm('Flush Permalinks?') ) {
                            try {
                                const res = await apiFetch({ path: '/wp-force-repair/v1/core/tools/flush-permalinks', method: 'POST' });
                                alert( res.message );
                            } catch(e) { alert(e.message); }
                        }
                    }}>
                        Flush Permalinks
                    </button>
                    
                     <button className="button button-secondary button-hero" onClick={ async () => {
                        if( confirm('Regenerate .htaccess?\n\nA backup of your current .htaccess will be created.') ) {
                            try {
                                const res = await apiFetch({ path: '/wp-force-repair/v1/core/tools/regenerate-htaccess', method: 'POST' });
                                alert( res.message + ( res.backup ? '\n' + res.backup : '' ) );
                            } catch(e) { alert(e.message); }
                        }
                    }}>
                        Regenerate .htaccess
                    </button>
                </div>
            </div>
        </div>
    );
};

export default SystemHealth;
