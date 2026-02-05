const { useState } = wp.element;
const apiFetch = wp.apiFetch;
import icons from '../icons'; 
import InstalledList from './InstalledList';
import InstallerOverlay from './InstallerOverlay';

const Dashboard = () => {
    const [ view, setView ] = useState( 'installed' ); 
    
    // Installer State
    const [ isInstalling, setIsInstalling ] = useState( false );
    const [ installLogs, setInstallLogs ] = useState( [] );
    const [ installStatus, setInstallStatus ] = useState( 'processing' ); // processing, success, error
    const [ installMessage, setInstallMessage ] = useState( '' );
    const [ installProgress, setInstallProgress ] = useState( '' ); // e.g. "1/5"

    const handleInstall = async ( slug, type, download_link, progress = '' ) => {
        setIsInstalling( true );
        setInstallProgress( progress );
        
        setInstallLogs( [ `Starting installation for ${slug}...`, `Type: ${type}` ] );
        if ( download_link ) {
             setInstallLogs( prev => [ ...prev, `Source: ${download_link}` ] );
        } else {
             setInstallLogs( prev => [ ...prev, `Source: Auto-detect from WP.org` ] );
        }
        
        setInstallStatus( 'processing' );
        setInstallMessage( '' );

        try {
            setInstallLogs( prev => [ ...prev, 'Downloading package...' ] );
            
            const response = await apiFetch( {
                path: '/wp-force-repair/v1/install',
                method: 'POST',
                data: { slug, type, download_link }
            } );

            // Append backend logs
            if ( response.logs && Array.isArray( response.logs ) ) {
                 setInstallLogs( prev => [ ...prev, ...response.logs ] );
            }

            setInstallStatus( 'success' );
            setInstallMessage( response.message || 'Installation completed successfully.' );
            setInstallLogs( prev => [ ...prev, 'Done.' ] );

        } catch ( error ) {
            if ( error.logs && Array.isArray( error.logs ) ) {
                 setInstallLogs( prev => [ ...prev, ...error.logs ] );
            }
            if ( error.errors && Array.isArray( error.errors ) ) {
                 setInstallLogs( prev => [ ...prev, ...error.errors.map( e => `ERROR: ${e}` ) ] );
            }
            
            setInstallStatus( 'error' );
            setInstallMessage( error.message || 'Installation failed.' );
            setInstallLogs( prev => [ ...prev, 'Process failed.' ] );
        }
    };

    // ...

    const closeOverlay = () => {
        setIsInstalling( false );
        setInstallLogs( [] );
        setInstallProgress( '' );
    };

    return (
        <div className="wrap">
            {/* ... */}
            <InstallerOverlay 
                isOpen={ isInstalling }
                logs={ installLogs }
                status={ installStatus }
                message={ installMessage }
                progress={ installProgress }
                onClose={ closeOverlay }
            />
            {/* ... */}

            <h2 className="nav-tab-wrapper">
                <a 
                    href="#" 
                    className={`nav-tab ${ view === 'installed_plugins' || view === 'installed' ? 'nav-tab-active' : '' }`}
                    onClick={(e) => { e.preventDefault(); setView('installed_plugins'); }}
                >
                    Installed Plugins
                </a>
                <a 
                    href="#" 
                    className={`nav-tab ${ view === 'installed_themes' ? 'nav-tab-active' : '' }`}
                    onClick={(e) => { e.preventDefault(); setView('installed_themes'); }}
                >
                    Installed Themes
                </a>
            </h2>

            <div className="wfr-content-wrap" style={{ marginTop: '20px' }}>
                { ( view === 'installed_plugins' || view === 'installed' ) && <InstalledList type="plugin" onReinstall={ handleInstall } /> }
                { view === 'installed_themes' && <InstalledList type="theme" onReinstall={ handleInstall } /> }
            </div>
        </div>
    );
};

export default Dashboard;
