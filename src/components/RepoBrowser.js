const RepoBrowser = ( { onInstall } ) => {
    const [ searchTerm, setSearchTerm ] = useState( '' );
    const [ results, setResults ] = useState( [] );
    const [ loading, setLoading ] = useState( false );
    const [ type, setType ] = useState( 'plugin' ); // 'plugin' or 'theme'

    const handleSearch = async ( e ) => {
        // ... (unchanged search logic)
        e.preventDefault();
        setLoading( true );
        try {
            const response = await apiFetch( {
                path: `/force-update/v1/search?type=${ type }&term=${ searchTerm }`
            } );
            
            if ( type === 'plugin' && response.plugins ) {
                 setResults( response.plugins );
            } else if ( type === 'theme' && response.themes ) {
                 setResults( response.themes );
            } else {
                 setResults( [] );
            }

        } catch ( error ) {
            console.error( error );
            setResults( [] );
        }
        setLoading( false );
    };

    return (
        <div className="wfr-view-container">
             <div className="wfr-search-header">
                <h2 className="wfr-title">Repository Browser</h2>
                <form onSubmit={ handleSearch } className="wfr-search-form">
                    <select 
                        value={type} 
                        onChange={ (e) => setType(e.target.value) }
                        className="wfr-input wfr-select"
                    >
                        <option value="plugin">Plugins</option>
                        <option value="theme">Themes</option>
                    </select>
                    <input 
                        type="text" 
                        className="wfr-input" 
                        placeholder={`Search ${type}s...`}
                        value={ searchTerm }
                        onChange={ (e) => setSearchTerm( e.target.value ) }
                    />
                    <button type="submit" className="wfr-btn">Search</button>
                </form>
             </div>

             <div className="wfr-results-grid">
                 { loading ? <div className="wfr-loading">Loading...</div> : (
                     results.map( ( item ) => (
                         <div key={ item.slug } className="wfr-card wfr-item-card">
                             <h3>{ item.name }</h3>
                             <p>v{ item.version }</p>
                             <div className="wfr-card-actions">
                                 <button 
                                     className="wfr-btn wfr-btn-sm"
                                     onClick={ () => onInstall( item.slug, type, item.download_link ) }
                                 >
                                     Force Install
                                 </button>
                             </div>
                         </div>
                     ) )
                 ) }
             </div>
        </div>
    );
};

export default RepoBrowser;
