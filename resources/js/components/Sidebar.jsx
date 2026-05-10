import React, { useState, useEffect } from 'react';

const Sidebar = ({ isOpen, onHover, onToggle, onLogout, user, activeSection, onSectionChange }) => {
    const [screenResolution, setScreenResolution] = useState(`${window.innerWidth}x${window.innerHeight}`);

    useEffect(() => {
        const handleResize = () => {
            setScreenResolution(`${window.innerWidth}x${window.innerHeight}`);
        };

        window.addEventListener('resize', handleResize);
        return () => window.removeEventListener('resize', handleResize);
    }, []);

    // Menú items basados en el rol del usuario
    const getMenuItems = () => {
        if (user.role === 'tecnico' || user.role === 'monitor') {
            return [
                { id: 'trabajos', label: 'Trabajos', icon: '🔧' },
                { id: 'historial', label: 'Historial de Trabajos', icon: '📋' }
            ];
        } else {
            // Administradores ven todo el menú
            return [
                { id: 'trabajos', label: 'Trabajos', icon: '🔧' },
                { id: 'clientes', label: 'Clientes', icon: '💬' },
                { id: 'analysis', label: 'Análisis', icon: '📊' },
                { id: 'dashboard', label: 'Gestión de Usuarios', icon: '👥' },
                { id: 'historial', label: 'Historial de Trabajos', icon: '📋' }         
            ];
        }
    };
    
    const menuItems = getMenuItems();

    const handleMenuItemClick = (itemId) => {
        onSectionChange(itemId);
        if (window.innerWidth < 1024) {
            onToggle();
        }
    };

    const handleMouseEnter = () => {
        onHover(true);
    };

    const handleMouseLeave = () => {
        onHover(false);
    };

    const handleLogoutClick = () => {
        onLogout();
        if (window.innerWidth < 1024) {
            onToggle();
        }
    };

    return (
        <aside 
            className={`sidebar ${isOpen ? 'sidebar-open' : ''}`}
            onMouseEnter={handleMouseEnter}
            onMouseLeave={handleMouseLeave}
        >
            <div className="sidebar-header">
                <div className="sidebar-user-info">
                    <div className="sidebar-avatar">
                        {user.name.charAt(0).toUpperCase()}
                    </div>
                    <div className="sidebar-user-details">
                        <span className="sidebar-user-name">{user.name}</span>
                    </div>
                </div>
                {window.innerWidth < 1024 && (
                    <button 
                        className="sidebar-close"
                        onClick={onToggle}
                        aria-label="Cerrar menú"
                    >
                        ×
                    </button>
                )}
            </div>

            <nav className="sidebar-nav">
                <ul className="sidebar-menu">
                    {menuItems.map(item => (
                        <li key={item.id} className="sidebar-menu-item">
                            <button
                                className={`sidebar-menu-link ${activeSection === item.id ? 'active' : ''}`}
                                onClick={() => handleMenuItemClick(item.id)}
                            >
                                <span className="sidebar-menu-icon">{item.icon}</span>
                                <span className="sidebar-menu-label">{item.label}</span>
                            </button>
                        </li>
                    ))}
                </ul>
            </nav>

            <div className="sidebar-footer">
                <button 
                    className="sidebar-logout-btn"
                    onClick={handleLogoutClick}
                >
                    <span className="sidebar-logout-label">Cerrar Sesión</span>
                </button>
                <div className="sidebar-resolution">
                    <span>Resolución: {screenResolution}</span>
                </div>
            </div>
        </aside>
    );
};

export default Sidebar;