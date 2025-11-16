import React from 'react';

const Sidebar = ({ isOpen, onHover, onToggle, onLogout, user, activeSection, onSectionChange }) => {
    // Menú items basados en el rol del usuario
    const getMenuItems = () => {
        if (user.role === 'tecnico') {
            // Técnicos ven "Trabajos" e "Historial"
            return [
                { id: 'trabajos', label: 'Trabajos', icon: '🔧' },
                { id: 'historial', label: 'Historial de Trabajos', icon: '📋' }
            ];
        } else {
            // Administradores ven todo el menú
            return [
                { id: 'trabajos', label: 'Trabajos', icon: '🔧' },
                { id: 'dashboard', label: 'Gestión de Usuarios', icon: '👥' },
                { id: 'historial', label: 'Historial de Trabajos', icon: '📋' }         
            ];
        }
    };
    
    const menuItems = getMenuItems();

    const handleMenuItemClick = (itemId) => {
        onSectionChange(itemId);
        // Cerrar sidebar en móviles al seleccionar item
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
        // Cerrar sidebar en móviles
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
                {/* Botón de cerrar SOLO en móviles/tablets */}
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
                {/* Botón de Logout en el footer del sidebar */}
                <button 
                    className="sidebar-logout-btn"
                    onClick={handleLogoutClick}
                >
                    <span className="sidebar-logout-label">Cerrar Sesión</span>
                </button>
            </div>
        </aside>
    );
};

export default Sidebar;