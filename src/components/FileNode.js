const { useState } = wp.element;

const FileNode = ({ file, depth, expandedPaths, fileCache, selectedFiles, onToggle, onToggleSelection, onView, onQuarantine }) => {
    const isExpanded = expandedPaths.includes( file.path );
    const children = fileCache[ file.path ] || [];
    const isLoading = isExpanded && ! fileCache[ file.path ];
    
    // Check protections
    let isProtected = false;
    // Simple protection check (can be expanded)
    if ( ['wp-admin', 'wp-includes', 'wp-content', 'wp-config.php'].includes(file.name) && depth === 0 ) {
         isProtected = true;
    }
    // Deep protection? e.g. plugins/themes
    if ( file.path.includes('wp-content') && ['themes', 'plugins', 'uploads'].includes(file.name) ) {
        isProtected = true;
    }

    // Rainbow Indent Colors
    const indentColors = ['#e5e5e5', '#a0a0a0', '#72aee6', '#b32d2e', '#e5801f', '#60bb46']; 
    const indentSize = 20;
    const basePadding = 10;
    
    const getIndentStyle = ( depth ) => {
        if ( depth === 0 ) return { paddingLeft: `${basePadding}px` };
        
        let gradients = [];
        for ( let i = 0; i < depth; i++ ) {
            const color = indentColors[ i % indentColors.length ];
            gradients.push( `linear-gradient(to right, ${color} 2px, transparent 2px)` );
        }
        
        return {
            paddingLeft: `${ basePadding + depth * indentSize }px`,
            backgroundImage: gradients.join(','),
            backgroundPosition: gradients.map( (_, i) => `${ basePadding + (i * indentSize) + 9 }px 0` ).join(','),
            backgroundSize: `2px 100%`,
            backgroundRepeat: 'no-repeat'
        };
    };

    return (
        <>
            <tr style={ isExpanded ? { backgroundColor: '#f6f7f7' } : {} }>
                <th scope="row" className="check-column">
                    <input 
                        type="checkbox" 
                        checked={ selectedFiles.includes( file.path ) } 
                        onChange={ () => onToggleSelection( file.path ) }
                        disabled={ isProtected }
                    />
                </th>
                <td style={ getIndentStyle(depth) }>
                    { file.type === 'directory' ? (
                        <a href="#" onClick={ (e) => { e.preventDefault(); onToggle( file.path ); } } style={{ display: 'flex', alignItems: 'center', fontWeight: '600' }}>
                            <span 
                                className={`dashicons dashicons-arrow-${ isExpanded ? 'down' : 'right' }-alt2`} 
                                style={{ marginRight: '2px', color: '#50575e', fontSize: '18px', width: '18px', height: '18px' }}
                            ></span>
                            <span className="dashicons dashicons-category" style={{ marginRight: '4px', color: '#72aee6' }}></span>
                            {file.name}
                        </a>
                    ) : (
                        <span style={{ display: 'flex', alignItems: 'center', paddingLeft: '22px' }}>
                            <span className="dashicons dashicons-media-text" style={{ marginRight: '4px', color: '#8c8f94' }}></span>
                            {file.name}
                        </span>
                    )}
                </td>
                <td>{file.type}</td>
                <td>{file.size}</td>
                <td>{file.perms}</td>
                <td>{file.mtime}</td>
                <td>
                    <div style={{ display: 'flex', gap: '5px' }}>
                        { file.type !== 'directory' && !isProtected && (
                            <button 
                                className="button button-small"
                                onClick={ () => onView(file) }
                            >
                                View
                            </button>
                        )}
                        { !isProtected ? (
                            <button 
                                className="button button-small" 
                                onClick={ () => onQuarantine([file.path]) }
                                style={{ color: '#d63638', borderColor: '#d63638' }}
                            >
                                Quarantine
                            </button>
                        ) : (
                           <span className="dashicons dashicons-lock" style={{ color: '#ccc', fontSize: '16px' }} title="Protected"></span>
                        )}
                    </div>
                </td>
            </tr>
            {/* Render Children */}
            { isExpanded && (
                isLoading ? (
                    <tr>
                        <td colSpan="7" style={{ ...getIndentStyle(depth + 1), color: '#666', fontStyle: 'italic' }}>
                            Loading...
                        </td>
                    </tr>
                ) : (
                    children.length === 0 ? (
                        <tr>
                             <td colSpan="7" style={{ ...getIndentStyle(depth + 1), color: '#666', fontStyle: 'italic' }}>
                                (Empty)
                            </td>
                        </tr>
                    ) : (
                        children.map( (child, j) => (
                            <FileNode 
                                key={ child.path + j } // Unique key combining path
                                file={ child }
                                depth={ depth + 1 }
                                expandedPaths={ expandedPaths }
                                fileCache={ fileCache }
                                selectedFiles={ selectedFiles }
                                onToggle={ onToggle }
                                onToggleSelection={ onToggleSelection }
                                onView={ onView }
                                onQuarantine={ onQuarantine }
                            />
                        ))
                    )
                )
            )}
        </>
    );
};

export default FileNode;
