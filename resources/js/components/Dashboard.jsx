import React, { useState, useEffect } from 'react';
import AdminPanel from './AdminPanel';
import Sidebar from './Sidebar';
import TrabajosPanel from './TrabajosPanel';
import HistorialPanel from './HistorialPanel';

const Dashboard = ({ user, onLogout, sidebarOpen, onSidebarHover, onSidebarToggle }) => {
    const [currentTime, setCurrentTime] = useState(new Date());
    const [activeSection, setActiveSection] = useState('trabajos'); // Cambiado para que todos vean 'trabajos' por defecto

    //Actualizar la hora cada segundo
    useEffect(() => {
        const timer = setInterval(() => {
            setCurrentTime(new Date());
        }, 1000);

        return () => clearInterval(timer);
    }, []);

    // Función para formatear la fecha
    const formatDate = (date) => {
        const options = { 
            year: 'numeric', 
            month: 'long', 
            day: 'numeric' 
        };
        return date.toLocaleDateString('es-ES', options);
    };

    // Función para formatear la hora
    const formatTime = (date) => {
        return date.toLocaleTimeString('es-ES', { 
            hour: '2-digit', 
            minute: '2-digit', 
            second: '2-digit',
            hour12: false 
        });
    };

    // Función para obtener el día de la semana
    const getWeekDay = (date) => {
        return date.toLocaleDateString('es-ES', { weekday: 'long' });
    };

    //Renderizar el panel según la sección activa y el rol del usuario
    const renderPanel = () => {
        if (user.role === 'tecnico' || user.role === 'monitor') {
            // Técnicos y Monitores pueden ver Trabajos e Historial
            switch (activeSection) {
                case 'trabajos':
                    return <TrabajosPanel user={user} />; // Pasar user
                case 'historial':
                    return <HistorialPanel user={user} />; // Pasar user
                default:
                    return <TrabajosPanel user={user} />; // Pasar user
            }
        } else {
            // Administradores pueden ver todos los paneles
            switch (activeSection) {
                case 'dashboard':
                    return <AdminPanel />;
                case 'trabajos':
                    return <TrabajosPanel user={user} />; // Pasar user
                case 'historial':
                    return <HistorialPanel user={user} />; // Pasar user
                default:
                    return <TrabajosPanel user={user} />; // Pasar user
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

            {/* Overlay para móviles */}
            {sidebarOpen && window.innerWidth < 1024 && (
                <div 
                    className="sidebar-overlay"
                    onClick={onSidebarToggle}
                ></div>
            )}
            
            {/* Contenido principal */}
            <div 
                className="main-content"
                onMouseEnter={() => onSidebarHover(false)}
            >
                <header className="dashboard-header">
                    <div className="header-content">
                        {/* Botón para abrir sidebar SOLO en celulares */}
                        {window.innerWidth < 1024 && (
                            <button 
                                className="sidebar-toggle-mobile"
                                onClick={onSidebarToggle}
                                aria-label="Abrir menú"
                            >
                                <span className="hamburger-icon">☰</span>
                            </button>
                        )}

                        {/* Fecha a la IZQUIERDA */}
                        <div className="header-date">
                            <div className="current-date">
                                {formatDate(currentTime)}
                            </div>
                            <div className="current-day">
                                {getWeekDay(currentTime).charAt(0).toUpperCase() + 
                                 getWeekDay(currentTime).slice(1)}
                            </div>
                        </div>

                        {/* Logo en el CENTRO */}
                        <div className="header-logo-container">
                            <img 
                                src="/images/logo.svg" 
                                alt="Logo del Sistema" 
                                className="header-logo"
                            />
                        </div>
                        
                        {/* Hora a la DERECHA */}
                        <div className="header-time">
                            <div className="current-time">
                                {formatTime(currentTime)}
                            </div>
                            <div className="time-timezone">
                                Hora Local
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