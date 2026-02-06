const { useState } = wp.element;

const Help = () => {
    const faqs = [
        {
            q: 'How does "Rescue Mode" work?',
            a: 'Rescue Mode is a standalone script that does NOT load WordPress. If your site has a "White Screen of Death", you can access this special page to disable plugins or switch themes manually. <br/><br/><strong>To activate:</strong> Rename <code>rescue/index.php.disabled</code> to <code>index.php</code> via FTP, then visit <code>yoursite.com/wp-content/plugins/wp-force-repair/rescue/</code>.'
        },
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
            q: 'How do I update this plugin?',
            a: 'This plugin supports auto-updates via GitHub. When a new release is properly tagged on the GitHub repository, it will appear in your Dashboard > Updates page just like any other plugin.'
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
