const { useState, useEffect } = wp.element;
const apiFetch = wp.apiFetch;
import icons from '../icons'; 
import InstalledList from './InstalledList';
import InstallerOverlay from './InstallerOverlay';
import CoreManager from './CoreManager';
import DatabaseHealth from './DatabaseHealth';
import SystemHealth from './SystemHealth';
import Help from './Help';

const Dashboard = () => {
    // Helper to get view from hash or default
    const getHashView = () => {
        const hash = window.location.hash.replace('#', '');
        const validViews = ['installed_plugins', 'installed_themes', 'core', 'database', 'system_health', 'help'];
        return validViews.includes(hash) ? hash : 'installed_plugins';
    };

    const [ view, setView ] = useState( getHashView() ); 

    // Sync state with hash changes
    useEffect(() => {
        const onHashChange = () => setView( getHashView() );
        window.addEventListener('hashchange', onHashChange);
        return () => window.removeEventListener('hashchange', onHashChange);
    }, []);

    // Update hash when view changes (if not already matching)
    useEffect(() => {
        if ( window.location.hash.replace('#', '') !== view ) {
            window.location.hash = view;
        }
    }, [ view ]); 
    
    // Installer State
    const [ isInstalling, setIsInstalling ] = useState( false );
    const [ installLogs, setInstallLogs ] = useState( [] );
    const [ installStatus, setInstallStatus ] = useState( 'processing' ); // processing, success, error
    const [ installMessage, setInstallMessage ] = useState( '' );
    const [ installProgress, setInstallProgress ] = useState( '' );

    const handleInstall = async ( slug, type, download_link = null, progress = '', action = 'install', file = null ) => {
        setIsInstalling( true );
        setInstallProgress( progress );
        
        const actionLabel = action === 'update' ? 'update' : 'installation';
        
        setInstallLogs( [ `Starting ${actionLabel} for ${slug}...`, `Type: ${type}` ] );
        if ( file ) {
             setInstallLogs( prev => [ ...prev, `Source: Manual Upload` ] );
        } else if ( download_link ) {
             setInstallLogs( prev => [ ...prev, `Source: ${download_link}` ] );
        } else if ( action === 'update' ) {
             setInstallLogs( prev => [ ...prev, `Method: Standard WordPress Upgrade` ] );
        } else {
             setInstallLogs( prev => [ ...prev, `Source: Auto-detect from WP.org` ] );
        }
        
        setInstallStatus( 'processing' );
        setInstallMessage( '' );

        try {
            if ( action === 'install' ) {
                setInstallLogs( prev => [ ...prev, file ? 'Uploading package...' : 'Downloading package...' ] );
            } else {
                setInstallLogs( prev => [ ...prev, 'Checking for updates...' ] );
            }
            
            let response;
            if ( file ) {
                const formData = new FormData();
                formData.append( 'slug', slug );
                formData.append( 'type', type );
                formData.append( 'package', file );

                // apiFetch handles formData automatically but we need to ensure it doesn't try to JSON stringify it.
                // wp.apiFetch documentation says if body is generic object, it stringifies. if FormData, it keeps it.
                // But we usually pass 'data'. passing 'body' directly works better for FormData.
                response = await apiFetch( {
                    path: '/wp-force-repair/v1/install/upload',
                    method: 'POST',
                    body: formData
                } );
            } else {
                const endpoint = action === 'update' ? '/wp-force-repair/v1/update/standard' : '/wp-force-repair/v1/install';
                response = await apiFetch( {
                    path: endpoint,
                    method: 'POST',
                    data: { slug, type, download_link }
                } );
            }

            // Append backend logs
            if ( response.logs && Array.isArray( response.logs ) ) {
                 setInstallLogs( prev => [ ...prev, ...response.logs ] );
            }

            setInstallStatus( 'success' );
            setInstallMessage( response.message || `${actionLabel} completed successfully.` );
            setInstallLogs( prev => [ ...prev, 'Done.' ] );

        } catch ( error ) {
            if ( error.logs && Array.isArray( error.logs ) ) {
                 setInstallLogs( prev => [ ...prev, ...error.logs ] );
            }
            if ( error.errors && Array.isArray( error.errors ) ) {
                 setInstallLogs( prev => [ ...prev, ...error.errors.map( e => `ERROR: ${e}` ) ] );
            }
            
            setInstallStatus( 'error' );
            setInstallMessage( error.message || `${actionLabel} failed.` );
            setInstallLogs( prev => [ ...prev, 'Process failed.' ] );
        }
    };

    const closeOverlay = () => {
        setIsInstalling( false );
        setInstallLogs( [] );
        setInstallProgress( '' );
    };

    return (
        <div className="wrap">
            <InstallerOverlay 
                isOpen={ isInstalling }
                logs={ installLogs }
                status={ installStatus }
                message={ installMessage }
                progress={ installProgress }
                onClose={ closeOverlay }
            />

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
                <a 
                    href="#" 
                    className={`nav-tab ${ view === 'core' ? 'nav-tab-active' : '' }`}
                    onClick={(e) => { e.preventDefault(); setView('core'); }}
                >
                    WordPress Core
                </a>
                <a 
                    href="#" 
                    className={`nav-tab ${ view === 'database' ? 'nav-tab-active' : '' }`}
                    onClick={(e) => { e.preventDefault(); setView('database'); }}
                >
                    Database Health
                </a>
                <a 
                    href="#" 
                    className={`nav-tab ${ view === 'system_health' ? 'nav-tab-active' : '' }`}
                    onClick={(e) => { e.preventDefault(); setView('system_health'); }}
                >
                    Tools
                </a>
                <a 
                    href="#" 
                    className={`nav-tab ${ view === 'help' ? 'nav-tab-active' : '' }`}
                    onClick={(e) => { e.preventDefault(); setView('help'); }}
                >
                    Help & Support
                </a>
            </h2>

            <div className="wfr-content-wrap" style={{ marginTop: '20px' }}>
                { ( view === 'installed_plugins' || view === 'installed' ) && <InstalledList type="plugin" onReinstall={ handleInstall } /> }
                { view === 'installed_themes' && <InstalledList type="theme" onReinstall={ handleInstall } /> }
                { view === 'core' && <CoreManager /> }
                { view === 'database' && <DatabaseHealth /> }
                { view === 'system_health' && <SystemHealth /> }
                { view === 'help' && <Help /> }
            </div>
        </div>
    );
};

export default Dashboard;
