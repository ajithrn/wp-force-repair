const { useState, useEffect } = wp.element;
const apiFetch = wp.apiFetch;
import Swal from 'sweetalert2';
import withReactContent from 'sweetalert2-react-content';

const MySwal = withReactContent(Swal);

const DatabaseHealth = () => {
    const [ stats, setStats ] = useState( null );
    const [ loading, setLoading ] = useState( true );
    const [ optimizing, setOptimizing ] = useState( false );
    const [ selectedTables, setSelectedTables ] = useState( [] );
    const [ recalculating, setRecalculating ] = useState( false );

    useEffect( () => {
        fetchStats();
    }, [] );

    const fetchStats = async () => {
        try {
            const res = await apiFetch({ path: '/wp-force-repair/v1/database/health' });
            setStats( res );
        } catch(e) {
            console.error(e);
            MySwal.fire({
                icon: 'error',
                title: 'Error fetching stats',
                text: e.message
            });
        } finally {
            setLoading( false );
        }
    };

    const handleRecalculate = async () => {
        setRecalculating( true );
        try {
             await apiFetch({
                path: '/wp-force-repair/v1/database/analyze',
                method: 'POST'
            });
            MySwal.fire({
                icon: 'success',
                title: 'Stats Updated',
                text: 'Database metadata has been recalculated.',
                timer: 2000,
                showConfirmButton: false
            });
            fetchStats();
        } catch(e) {
            MySwal.fire({
                icon: 'error',
                title: 'Update Failed',
                text: e.message
            });
        }
        setRecalculating( false );
    };

    const handleOptimize = async ( targetTables = null ) => {
        const result = await MySwal.fire({
            title: 'Optimize Database?',
            text: 'It is highly recommended to backup your database before proceeding.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes, Optimize',
            confirmButtonColor: '#2271b1',
            footer: '<a href="#system_health">Go to Backup Manager</a>'
        });

        if ( ! result.isConfirmed ) return;

        setOptimizing( true );
        
        const tablesToOptimize = targetTables || [];
        
        try {
            const res = await apiFetch({
                path: '/wp-force-repair/v1/database/optimize',
                method: 'POST',
                data: {
                    tables: tablesToOptimize.length > 0 ? tablesToOptimize : undefined
                }
            });
            
            if ( res.success ) {
                MySwal.fire({
                    icon: 'success',
                    title: 'Optimization Complete!',
                    text: res.message,
                    timer: 2000,
                    showConfirmButton: false
                });
                fetchStats();
            }
        } catch(e) {
            MySwal.fire({
                icon: 'error',
                title: 'Optimization Failed',
                text: e.message
            });
        }
        setOptimizing( false );
        if (targetTables && targetTables.length > 0) setSelectedTables([]); 
    };

    const toggleSelectAll = ( e ) => {
        if ( e.target.checked && stats ) {
            setSelectedTables( stats.all_tables.map( t => t.name ) );
        } else {
            setSelectedTables( [] );
        }
    };

    const toggleTable = ( name ) => {
        if ( selectedTables.includes( name ) ) {
            setSelectedTables( selectedTables.filter( t => t !== name ) );
        } else {
            setSelectedTables( [ ...selectedTables, name ] );
        }
    };

    if ( loading ) {
        return <div className="notice notice-info inline" style={{ marginTop: '20px' }}><p>Loading database details...</p></div>;
    }

    if ( ! stats ) {
        return <div className="notice notice-error"><p>Failed to load statistics.</p></div>;
    }

    return (
        <div className="wfr-database-health-view" style={{ marginTop: '20px' }}>
            <div className="wfr-section-header" style={{ marginBottom: '20px' }}>
                <h2 className="title">Database Health</h2>
                <p className="description">Monitor your database performance and clean up overhead.</p>
            </div>

            <div className="notice notice-warning inline" style={{ marginBottom: '20px', borderLeft: '4px solid #dba617' }}>
                <p><strong>⚠️ Important:</strong> Always create a <strong>database backup</strong> before performing optimization or deletion tasks.</p>
            </div>

            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(280px, 1fr))', gap: '20px', marginBottom: '20px' }}>
                {/* Cards remain same... */}
                <div className="card" style={{ padding: '20px', borderLeft: '4px solid #2271b1' }}>
                    <h3 style={{ margin: 0, fontSize: '14px', color: '#646970' }}>Total Size</h3>
                    <div style={{ fontSize: '24px', fontWeight: 'bold', marginTop: '5px' }}>
                        { stats.total_size }
                        { (stats.total_size === '0 B' || stats.total_size === 'N/A') && (
                            <button 
                                className="button button-small" 
                                style={{ marginLeft: '10px', verticalAlign: 'middle', fontSize: '11px' }}
                                onClick={ handleRecalculate }
                                disabled={ recalculating }
                            >
                                { recalculating ? 'Updating...' : 'Fix Missing Stats' }
                            </button>
                        ) }
                    </div>
                    <p style={{ margin: '5px 0 0', fontSize: '12px' }}>{ stats.table_count } Tables ({ stats.engines.InnoDB || 0 } InnoDB, { stats.engines.MyISAM || 0 } MyISAM)</p>
                </div>

                <div className="card" style={{ padding: '20px', borderLeft: stats.total_overhead_raw > 0 ? '4px solid #d63638' : '4px solid #00a32a' }}>
                    <h3 style={{ margin: 0, fontSize: '14px', color: '#646970' }}>Overhead</h3>
                    <div style={{ fontSize: '24px', fontWeight: 'bold', marginTop: '5px' }}>{ stats.total_overhead }</div>
                    <p style={{ margin: '5px 0 0', fontSize: '12px' }}>
                        { stats.total_overhead_raw > 0 ? 'Wasted space checking needed.' : 'Database is optimized.' }
                    </p>
                </div>

                <div className="card" style={{ padding: '20px', borderLeft: stats.autoload_size_raw > 1000000 ? '4px solid #dba617' : '4px solid #2271b1' }}>
                    <h3 style={{ margin: 0, fontSize: '14px', color: '#646970' }}>Autoload Size</h3>
                    <div style={{ fontSize: '24px', fontWeight: 'bold', marginTop: '5px' }}>{ stats.autoload_size }</div>
                    <p style={{ margin: '5px 0 0', fontSize: '12px' }}>Data loaded on every page load.</p>
                </div>
            </div>

            <div className="card" style={{ padding: '0', overflow: 'hidden', maxWidth: '100%' }}>
                <div style={{ padding: '15px 20px', background: '#f6f7f7', borderBottom: '1px solid #c3c4c7', display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                    <h3 style={{ margin: 0, fontSize: '14px' }}>Database Tables</h3>
                    <div style={{ display: 'flex', gap: '10px' }}>
                         { selectedTables.length > 0 && (
                            <button 
                                className="button button-primary" 
                                onClick={ () => handleOptimize(selectedTables) }
                                disabled={ optimizing }
                            >
                                { optimizing ? 'Optimizing...' : `Optimize Selected (${selectedTables.length})` }
                            </button>
                        )}
                        <button 
                            className="button button-secondary" 
                            disabled={ stats.total_overhead_raw === 0 || optimizing }
                            onClick={ () => handleOptimize() }
                        >
                            Optimize All With Overhead
                        </button>
                    </div>
                </div>
                
                { stats.all_tables && stats.all_tables.length > 0 ? (
                    <table className="widefat striped" style={{ border: 'none', width: '100%' }}>
                        <thead>
                            <tr>
                                <td id="cb" className="manage-column column-cb check-column">
                                    <input type="checkbox" onChange={ toggleSelectAll } checked={ selectedTables.length === stats.all_tables.length } />
                                </td>
                                <th style={{ paddingLeft: '10px', textAlign: 'left' }}>Table Name</th>
                                <th style={{ textAlign: 'center' }}>Rows</th>
                                <th style={{ textAlign: 'center' }}>Size</th>
                                <th style={{ textAlign: 'center' }}>Overhead</th>
                                <th style={{ textAlign: 'center' }}>Engine</th>
                                <th style={{ textAlign: 'right', paddingRight: '20px' }}>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            { stats.all_tables.map( ( table, i ) => {
                                const isCore = ['users', 'usermeta', 'posts', 'postmeta', 'comments', 'commentmeta', 'options', 'terms', 'term_taxonomy', 'term_relationships', 'termmeta', 'links'].some( base => table.name === stats.prefix + base );
                                
                                return (
                                <tr key={ i }>
                                    <th scope="row" className="check-column">
                                        <input 
                                            type="checkbox" 
                                            checked={ selectedTables.includes( table.name ) }
                                            onChange={ () => toggleTable( table.name ) }
                                        />
                                    </th>
                                    <td style={{ paddingLeft: '10px', fontWeight: '500', textAlign: 'left' }}>{ table.name }</td>
                                    <td style={{ textAlign: 'center' }}>{ table.rows }</td>
                                    <td style={{ textAlign: 'center' }}>{ table.size }</td>
                                    <td style={{ textAlign: 'center', color: table.overhead_raw > 0 ? '#d63638' : 'inherit' }}>{ table.overhead }</td>
                                    <td style={{ textAlign: 'center' }}>{ table.engine }</td>
                                    <td style={{ textAlign: 'right', paddingRight: '20px' }}>
                                        <button 
                                            className="button button-small"
                                            style={{ marginRight: '5px' }}
                                            onClick={ () => handleOptimize([table.name]) }
                                            disabled={ optimizing }
                                        >
                                            Optimize
                                        </button>
                                        
                                        { ! isCore && (
                                            <button 
                                                className="button button-link-delete"
                                                onClick={ async () => {
                                                    // ... (delete logic remains same) ...
                                                    const confirm = await MySwal.fire({
                                                        title: 'Delete Table?',
                                                        html: `Are you sure you want to drop <b>${table.name}</b>?<br>This cannot be undone!`,
                                                        icon: 'warning',
                                                        showCancelButton: true,
                                                        confirmButtonColor: '#d63638',
                                                        confirmButtonText: 'Yes, Delete it!'
                                                    });
                                                    
                                                    if ( confirm.isConfirmed ) {
                                                        const userConfirm = await MySwal.fire({
                                                            input: 'text',
                                                            inputLabel: `Type "${table.name}" to confirm`,
                                                            inputPlaceholder: table.name,
                                                            showCancelButton: true,
                                                            confirmButtonText: 'Permanently Delete',
                                                            confirmButtonColor: '#d63638',
                                                             preConfirm: (value) => {
                                                                if (value !== table.name) {
                                                                    MySwal.showValidationMessage('Table name does not match')
                                                                }
                                                            }
                                                        });

                                                        if ( userConfirm.isConfirmed ) {
                                                            setOptimizing(true); 
                                                            try {
                                                                await apiFetch({
                                                                    path: '/wp-force-repair/v1/database/drop-table',
                                                                    method: 'POST',
                                                                    data: { table: table.name }
                                                                });
                                                                MySwal.fire('Deleted', `Table ${table.name} has been dropped.`, 'success');
                                                                fetchStats();
                                                            } catch(e) {
                                                                MySwal.fire('Error', e.message, 'error');
                                                            }
                                                            setOptimizing(false);
                                                        }
                                                    }
                                                }}
                                            >
                                                Delete
                                            </button>
                                        ) }
                                         { isCore && <span className="dashicons dashicons-lock" style={{ color: '#ccc', verticalAlign: 'middle' }} title="Protected Core Table"></span> }
                                    </td>
                                </tr>
                                );
                            }) }
                        </tbody>
                    </table>
                ) : (
                    <div style={{ padding: '20px' }}>No tables found.</div>
                )}
            </div>
            
            <div style={{ marginTop: '20px', fontSize: '12px', color: '#666', textAlign: 'right' }}>
                MySQL Version: { stats.mysql_version } | Table Prefix: { stats.prefix }
            </div>
        </div>
    );
};

export default DatabaseHealth;
