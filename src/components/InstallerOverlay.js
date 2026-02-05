const { useEffect, useRef } = wp.element;

const InstallerOverlay = ( { logs, isOpen, onClose, status, message, progress } ) => {
    const endRef = useRef( null );

    useEffect( () => {
        // Auto-scroll to bottom
        if ( endRef.current ) {
            endRef.current.scrollIntoView( { behavior: "smooth" } );
        }
    }, [ logs ] );

    if ( ! isOpen ) return null;

    const handleBackdropClick = ( e ) => {
        // Only allow closing via backdrop if NOT processing
        if ( status !== 'processing' && e.target.className === 'wfr-overlay-backdrop' ) {
            onClose();
        }
    };

    return (
        <div className="wfr-overlay-backdrop" onClick={ handleBackdropClick }>
            <div className="wfr-modal wfr-terminal-modal">
                <div className="wfr-modal-header">
                    <h3>
                        Installation Progress 
                        { progress && <span style={{ fontSize: '12px', opacity: 0.8, marginLeft: '10px' }}>({progress})</span> }
                    </h3>
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
                        <span>
                            { status === 'success' ? '✅ Success: ' : '❌ Error: ' } { message || 'Operation completed.' }
                        </span>
                        <button className="button button-secondary" onClick={ onClose }>Close</button>
                     </div>
                ) }
            </div>
        </div>
    );
};

export default InstallerOverlay;
