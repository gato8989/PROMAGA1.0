import React, { useState, useEffect } from 'react';
import AdminPanel from './AdminPanel';
import DashboardPanel from './DashboardPanel';
import Sidebar from './Sidebar';
import TrabajosPanel from './TrabajosPanel';
import HistorialPanel from './HistorialPanel';
import ClientesPanel from './ClientesPanel';

const Dashboard = ({ user, onLogout, sidebarOpen, onSidebarHover, onSidebarToggle }) => {
    const [currentTime, setCurrentTime] = useState(new Date());
    const [activeSection, setActiveSection] = useState('trabajos');

    // Actualizar la hora cada segundo
    useEffect(() => {
        const timer = setInterval(() => {
            setCurrentTime(new Date());
        }, 1000);
        return () => clearInterval(timer);
    }, []);

    const formatDate = (date) => {
        const options = { 
            year: 'numeric', 
            month: 'long', 
            day: 'numeric' 
        };
        return date.toLocaleDateString('es-ES', options);
    };

    const formatTime = (date) => {
        return date.toLocaleTimeString('es-ES', { 
            hour: '2-digit', 
            minute: '2-digit', 
            second: '2-digit',
            hour12: false 
        });
    };

    const getWeekDay = (date) => {
        return date.toLocaleDateString('es-ES', { weekday: 'long' });
    };

    // Renderizar el panel según la sección activa y el rol del usuario
    const renderPanel = () => {
        if (user.role === 'tecnico' || user.role === 'monitor') {
            switch (activeSection) {
                case 'trabajos':
                    return <TrabajosPanel user={user} />;
                case 'historial':
                    return <HistorialPanel user={user} />;
                default:
                    return <TrabajosPanel user={user} />;
            }
        } else {
            switch (activeSection) {
                case 'analysis':
                    return <DashboardPanel />;
                case 'dashboard':
                    return <AdminPanel />;
                case 'trabajos':
                    return <TrabajosPanel user={user} />;
                case 'historial':
                    return <HistorialPanel user={user} />;
                case 'clientes':
                    return <ClientesPanel user={user} />;
                default:
                    return <TrabajosPanel user={user} />;
            }
        }
    };

    return (
        <div className="dashboard-container">
            <Sidebar 
                isOpen={sidebarOpen}
                onHover={onSidebarHover}
                onToggle={onSidebarToggle}
                onLogout={onLogout}
                user={user}
                activeSection={activeSection}
                onSectionChange={setActiveSection}
            />

            {sidebarOpen && window.innerWidth < 1024 && (
                <div 
                    className="sidebar-overlay"
                    onClick={onSidebarToggle}
                ></div>
            )}
            
            <div 
                className="main-content"
                onMouseEnter={() => onSidebarHover(false)}
            >
                <header className="dashboard-header">
                    <div className="header-content">
                        {window.innerWidth < 1024 && (
                            <button 
                                className="sidebar-toggle-mobile"
                                onClick={onSidebarToggle}
                                aria-label="Abrir menú"
                            >
                                <span className="hamburger-icon">☰</span>
                            </button>
                        )}

                        <div className="header-date">
                            <div className="current-date">
                                {formatDate(currentTime)}
                            </div>
                            <div className="current-day">
                                {getWeekDay(currentTime).charAt(0).toUpperCase() + 
                                 getWeekDay(currentTime).slice(1)}
                            </div>
                        </div>

                        <div className="header-logo-container">
                            <img 
                                src="/images/logo.svg" 
                                alt="Logo del Sistema" 
                                className="header-logo"
                            />
                        </div>
                        
                        <div className="header-time">
                            <div className="current-time">
                                {formatTime(currentTime)}
                            </div>
                        </div>
                    </div>
                </header>
                
                <main className="dashboard-main">
                    {renderPanel()}
                </main>
            </div>
        </div>
    );
};

export default Dashboard;