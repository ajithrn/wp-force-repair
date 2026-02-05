import logo from '../assets/logo.png';
import { useState } from '@wordpress/element';
import icons from '../icons'; 
import RepoBrowser from './RepoBrowser';
import InstalledList from './InstalledList';
import PremiumList from './PremiumList';
import InstallerOverlay from './InstallerOverlay';
import apiFetch from '@wordpress/api-fetch';

const Dashboard = () => {
    const [ view, setView ] = useState( 'search' ); 
    
    // Installer State
    const [ isInstalling, setIsInstalling ] = useState( false );
    const [ installLogs, setInstallLogs ] = useState( [] );
    const [ installStatus, setInstallStatus ] = useState( 'processing' ); // processing, success, error
    const [ installMessage, setInstallMessage ] = useState( '' );

    const handleInstall = async ( slug, type, download_link ) => {
        setIsInstalling( true );
        setInstallLogs( [ `Starting installation for ${slug}...`, `Type: ${type}`, `Source: ${download_link}` ] );
        setInstallStatus( 'processing' );
        setInstallMessage( '' );

        try {
            setInstallLogs( prev => [ ...prev, 'Downloading package...' ] );
            
            const response = await apiFetch( {
                path: '/force-update/v1/install',
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

    const closeOverlay = () => {
        setIsInstalling( false );
        setInstallLogs( [] );
    };

    return (
        <div className="wfr-dashboard-layout">
            <InstallerOverlay 
                isOpen={ isInstalling }
                logs={ installLogs }
                status={ installStatus }
                message={ installMessage }
                onClose={ closeOverlay }
            />

            <header className="wfr-header">
                <div className="wfr-brand">
                    <img src={logo} alt="WP Force Repair" className="wfr-logo-img" style={{ height: '40px', marginRight: '1rem' }} />
                    <h1>WP Force Repair</h1>
                </div>
                <div className="wfr-header-actions">
                   {/* User Profile or Settings Icon could go here */}
                </div>
            </header>

            <div className="wfr-main-container">
                <aside className="wfr-sidebar">
                    <nav className="wfr-nav">
                        <button 
                            className={`wfr-nav-item ${ view === 'search' ? 'active' : '' }`}
                            onClick={() => setView('search')}
                        >
                            <span className="icon">🔍</span> Repo Browser
                        </button>
                        <button 
                            className={`wfr-nav-item ${ view === 'installed' ? 'active' : '' }`}
                            onClick={() => setView('installed')}
                        >
                            <span className="icon">📦</span> Installed
                        </button>
                        <button 
                            className={`wfr-nav-item ${ view === 'premium' ? 'active' : '' }`}
                            onClick={() => setView('premium')}
                        >
                            <span className="icon">💎</span> Premium
                        </button>
                    </nav>
                </aside>

                <main className="wfr-content">
                    { view === 'search' && <RepoBrowser onInstall={ handleInstall } /> }
                    { view === 'installed' && <InstalledList /> }
                    { view === 'premium' && <PremiumList /> }
                </main>
            </div>
        </div>
    );
};

export default Dashboard;
