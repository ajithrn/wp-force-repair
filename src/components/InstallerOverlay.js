import { useEffect, useRef } from '@wordpress/element';

const InstallerOverlay = ( { logs, isOpen, onClose, status, message } ) => {
    const endRef = useRef( null );

    useEffect( () => {
        endRef.current?.scrollIntoView( { behavior: "smooth" } );
    }, [ logs ] );

    if ( ! isOpen ) return null;

    return (
        <div className="wfr-overlay-backdrop">
            <div className="wfr-modal wfr-terminal-modal">
                <div className="wfr-modal-header">
                    <h3>Installation Progress</h3>
                    { status !== 'processing' && (
                        <button className="wfr-close-btn" onClick={ onClose }>&times;</button>
                    ) }
                </div>
                <div className="wfr-terminal-window">
                    { logs.map( ( log, index ) => (
                        <div key={ index } className="wfr-log-line">
                            <span className="wfr-prompt">$</span> { log }
                        </div>
                    ) ) }
                    { status === 'processing' && (
                        <div className="wfr-log-line wfr-blink">_</div>
                    ) }
                    <div ref={ endRef } />
                </div>
                { status !== 'processing' && (
                     <div className={`wfr-status-footer ${status}`}>
                        { status === 'success' ? '✅ Success: ' : '❌ Error: ' } { message }
                        <button className="wfr-btn wfr-btn-sm" onClick={ onClose }>Close</button>
                     </div>
                ) }
            </div>
        </div>
    );
};

export default InstallerOverlay;
