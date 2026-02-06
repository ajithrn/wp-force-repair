const { useState, useEffect } = wp.element;
const apiFetch = wp.apiFetch;
import Swal from 'sweetalert2';
import withReactContent from 'sweetalert2-react-content';

const MySwal = withReactContent(Swal);

const Help = () => {
    const faqs = [
        {
            q: 'Is the "Force Re-install" safe?',
            a: 'Yes. It forces WordPress to download a fresh copy of the plugin/theme from the official repository and overwrites your current files. This cleans up any corrupted or infected files. Your settings (database) are preserving.'
        },
        {
            q: 'My backup Zip failed. Why?',
            a: 'Large sites often time out when zipping via PHP. We now try to use the server\'s native <code>zip</code> command first. If it still fails, check if you have enough disk space.'
        },
        {
            q: 'Why are there "Unknown Files" in Core?',
            a: 'The plugin scans your <code>wp-admin</code> and <code>wp-includes</code> folders against the official checksums. Any file NOT in the official release is flagged. These are often malware or leftover files from old updates.'
        },
        {
            q: 'Where are "Quarantined" files stored?',
            a: 'Files flagged as suspicious are moved to: <br/><code>/wp-content/uploads/wfr-quarantine/</code>.<br/>They are not deleted immediately, so you can restore them if needed.'
        },
        {
            q: 'Where are my Backups stored?',
            a: 'Database dumps and Zip archives are stored in: <br/><code>/wp-content/uploads/wfr-backups/</code>.<br/>This directory is protected with an .htaccess file to prevent public access.'
        },
        {
            q: 'How do I restore a Quarantine file?',
            a: 'Go to the "WordPress Core" tab and check the "Quarantine" section at the bottom. Click "Restore" next to any file to move it back to its original location.'
        }
    ];

    return (
        <div className="wfr-help-view" style={{ marginTop: '20px' }}>
            <div className="wfr-section-header">
                <h2 className="title">Help & Support</h2>
                <p className="description">Common questions and guides for using WP Force Repair.</p>
            </div>

            <PluginUpdateCard />

            <div className="wfr-card" style={{ marginTop: '20px', padding: '0' }}>
                { faqs.map((faq, i) => (
                    <AccordionItem key={i} question={faq.q} answer={faq.a} />
                ))}
            </div>

            <div className="wfr-card" style={{ marginTop: '20px', padding: '20px', borderLeft: '4px solid #72aee6' }}>
                <h3>Need more help?</h3>
                <p>
                    Please open an issue on the <a href="https://github.com/ajithrn/wp-force-repair/issues" target="_blank">GitHub Repository</a>.
                </p>
            </div>
        </div>
    );
};

const PluginUpdateCard = () => {
    const [ checking, setChecking ] = useState( false );
    const [ status, setStatus ] = useState( null );

    const checkUpdates = async () => {
        setChecking( true );
        try {
            const res = await apiFetch({ path: '/wp-force-repair/v1/check-update' });
            setStatus( res );
        } catch ( e ) {
            setStatus({ error: e.message });
        }
        setChecking( false );
    };
    
    const handleUpdate = async () => {
         const result = await MySwal.fire({
            title: 'Update Plugin?',
            text: `Install version ${status.new_version}?`,
            icon: 'info',
            showCancelButton: true,
            confirmButtonText: 'Yes, Update Now'
        });

        if ( ! result.isConfirmed ) return;
        
        // Use existing install endpoint
        // Slug for this plugin is 'wp-force-repair/wp-force-repair' or similar, but GitHubUpdater handles specifics
        // We know the slug relative to plugins dir is likely 'wp-force-repair/wp-force-repair', 
        // OR we can just pass the slug 'wp-force-repair' and let standard installer handle it?
        // Wait, standard installer expects WP.org slug. 
        // But our customized UpdateController CAN handle download_link directly.
        
        MySwal.fire({ title: 'Updating...', didOpen: () => MySwal.showLoading() });
        
        try {
             await apiFetch({ 
                path: '/wp-force-repair/v1/install',
                method: 'POST',
                data: {
                    type: 'plugin',
                    slug: 'wp-force-repair', // This might be tricky if it tries to fetch from repo, but providing download_link overrides that.
                    download_link: status.download_link
                }
            });
            
            await MySwal.fire( 'Updated!', 'Plugin updated successfully. Reloading...', 'success' );
            window.location.reload();
        } catch ( e ) {
            MySwal.fire( 'Update Failed', e.message, 'error' );
        }
    };

    return (
        <div className="wfr-card" style={{ marginTop: '20px', padding: '20px', display: 'flex', alignItems: 'center', justifyContent: 'space-between', borderLeft: status?.has_update ? '4px solid #d63638' : '4px solid #00a32a' }}>
            <div>
                <h3 style={{ margin: '0 0 5px 0' }}>Plugin Status</h3>
                { ! status && <p style={{ margin: 0, color: '#666' }}>Force check for plugin updates from GitHub.</p> }
                
                { status && status.error && <p style={{ margin: 0, color: '#d63638' }}>Error: { status.error }</p> }
                
                { status && ! status.error && (
                    <div>
                        <p style={{ margin: 0, fontWeight: 600, color: status.has_update ? '#d63638' : '#00a32a' }}>
                            { status.has_update 
                                ? `Update Available: v${status.new_version}` 
                                : `You are on the latest version (v${status.current_version})` 
                            }
                        </p>
                        { status.has_update && <p style={{ fontSize: '12px', margin: '5px 0 0 0' }}>Current: v{status.current_version}</p> }
                    </div>
                )}
            </div>
            <div>
                { ! status || ! status.has_update ? (
                     <button className="button button-secondary" disabled={ checking } onClick={ checkUpdates }>
                        { checking ? 'Checking...' : 'Check for Updates' }
                    </button>
                ) : (
                    <button className="button button-primary" onClick={ handleUpdate }>
                        Update Now
                    </button>
                )}
            </div>
        </div>
    );
};

const AccordionItem = ({ question, answer }) => {
    const [ isOpen, setIsOpen ] = useState(false);

    return (
        <div className="wfr-faq-item" style={{ borderBottom: '1px solid #f0f0f1' }}>
            <button 
                onClick={() => setIsOpen(!isOpen)}
                style={{
                    width: '100%',
                    background: 'none',
                    border: 'none',
                    textAlign: 'left',
                    padding: '20px',
                    fontSize: '14px',
                    fontWeight: 600,
                    cursor: 'pointer',
                    display: 'flex',
                    justifyContent: 'space-between',
                    alignItems: 'center'
                }}
            >
                {question}
                <span className="dashicons dashicons-arrow-down-alt2" style={{ transform: isOpen ? 'rotate(180deg)' : 'none', transition: '0.2s' }}></span>
            </button>
            { isOpen && (
                <div 
                    style={{ padding: '0 20px 20px 20px', color: '#50575e', lineHeight: '1.5' }} 
                    dangerouslySetInnerHTML={{ __html: answer }}
                />
            )}
        </div>
    );
};

export default Help;
