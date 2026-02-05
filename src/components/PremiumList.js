import { useState } from '@wordpress/element';

const PremiumList = () => {
    // Mock data for Premium Plugins showcase
    const premiumPlugins = [
        { id: 1, name: 'Advanced Pro Pack', version: '2.4.0', author: 'EliteDevs', status: 'Not Installed' },
        { id: 2, name: 'Security Fortress', version: '1.1.5', author: 'SecTeam', status: 'Active' },
    ];

    return (
        <div className="wfr-view-container">
            <h2 className="wfr-title">Premium Extensions</h2>
            <p className="wfr-subtitle">Manage your paid and premium plugins here.</p>

            <div className="wfr-results-grid">
                { premiumPlugins.map( ( item ) => (
                     <div key={ item.id } className="wfr-card wfr-item-card wfr-premium-card">
                        <div className="wfr-premium-badge">PRO</div>
                        <h3>{ item.name }</h3>
                        <p> by { item.author }</p>
                        <div className="wfr-meta">
                            <span className={`wfr-status ${item.status.toLowerCase().replace(' ', '-')}`}>
                                { item.status }
                            </span>
                        </div>
                        <div className="wfr-card-actions">
                            <button className="wfr-btn wfr-btn-outline wfr-btn-sm">Configure</button>
                        </div>
                    </div>
                ) ) }
            </div>
        </div>
    );
};

export default PremiumList;
