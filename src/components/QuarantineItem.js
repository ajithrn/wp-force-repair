const { useState } = wp.element;

const QuarantineItem = ({ data, onDeleteFolder, onRestore, onDeleteFile }) => {
    const [ isOpen, setIsOpen ] = useState( false );

    return (
        <div style={{ marginBottom: '10px', border: '1px solid #c3c4c7', background: '#fff' }}>
            <div 
                onClick={ () => setIsOpen( ! isOpen ) }
                style={{ 
                    padding: '10px 15px',
                    background: '#f6f7f7',
                    borderBottom: isOpen ? '1px solid #c3c4c7' : 'none',
                    display: 'flex',
                    justifyContent: 'space-between',
                    alignItems: 'center',
                    cursor: 'pointer',
                    userSelect: 'none'
                }}
            >
                <div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
                    <span className={`dashicons dashicons-arrow-${ isOpen ? 'down-alt2' : 'right-alt2' }`} style={{ color: '#50575e' }}></span>
                    <span className="dashicons dashicons-category" style={{ color: '#737373' }}></span>
                    <span style={{ fontWeight: 600, fontSize: '13px' }}>Backup: { data.timestamp }</span>
                    <span style={{ background: '#dcdcde', borderRadius: '10px', padding: '2px 8px', fontSize: '11px', color: '#50575e' }}>
                        { data.files.length } files
                    </span>
                </div>
                
                <button 
                    className="button button-small button-link-delete"
                    onClick={ (e) => { e.stopPropagation(); onDeleteFolder( data.timestamp ); } }
                    style={{ fontWeight: 'normal', color: '#b32d2e' }}
                >
                    Delete Folder
                </button>
            </div>
            
            { isOpen && (
                <table className="wp-list-table widefat fixed striped" style={{ border: 'none', boxShadow: 'none' }}>
                    <tbody>
                        { data.files.map( ( file, j ) => (
                            <tr key={ j }>
                                <td>
                                    <span style={{ display: 'flex', alignItems: 'center' }}>
                                        <span className="dashicons dashicons-media-text" style={{ marginRight: '4px', color: '#8c8f94' }}></span>
                                        {file.name}
                                    </span>
                                </td>
                                <td style={{ width: '80px' }}>{ file.size }</td>
                                <td style={{ textAlign: 'right', width: '200px' }}>
                                    <button className="button button-small" onClick={ () => onRestore( file.path ) } style={{ marginRight: '5px' }}>Restore</button>
                                    <button className="button button-small button-link-delete" onClick={ () => onDeleteFile( file.path ) } style={{ color: '#a00' }}>Delete</button>
                                </td>
                            </tr>
                        ) ) }
                    </tbody>
                </table>
            )}
        </div>
    );
};

export default QuarantineItem;
