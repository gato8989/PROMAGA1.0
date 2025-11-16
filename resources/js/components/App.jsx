import React, { useState, useEffect } from 'react';
import Login from './Login';
import Dashboard from './Dashboard';
import axios from 'axios';

function App() {
    const [user, setUser] = useState(null);
    const [loading, setLoading] = useState(true);
    const [sidebarOpen, setSidebarOpen] = useState(false);

    // Configurar Axios globalmente
    useEffect(() => {
        axios.interceptors.request.use(
            (config) => {
                const token = localStorage.getItem('token');
                if (token) {
                    config.headers.Authorization = `Bearer ${token}`;
                }
                return config;
            },
            (error) => {
                return Promise.reject(error);
            }
        );

        axios.interceptors.response.use(
            (response) => response,
            (error) => {
                if (error.response?.status === 401) {
                    localStorage.removeItem('token');
                    setUser(null);
                }
                return Promise.reject(error);
            }
        );

        checkAuth();
    }, []);

    const checkAuth = async () => {
        const token = localStorage.getItem('token');
        
        if (token) {
            try {
                const response = await axios.get('/api/user');
                setUser(response.data.user);
            } catch (error) {
                console.error('Error verificando autenticación:', error);
                localStorage.removeItem('token');
            }
        }
        setLoading(false);
    };

    const handleLogin = (userData) => {
        setUser(userData);
    };

    const handleLogout = async () => {
        try {
            await axios.post('/api/logout');
        } catch (error) {
            console.error('Error al cerrar sesión:', error);
        } finally {
            localStorage.removeItem('token');
            setUser(null);
            setSidebarOpen(false);
        }
    };

    const handleSidebarHover = (isHovering) => {
        // Solo abrir/cerrar con hover en desktop
        if (window.innerWidth >= 1024) {
            setSidebarOpen(isHovering);
        }
    };

    const handleSidebarToggle = () => {
        // Solo para móviles/tablets
        if (window.innerWidth < 1024) {
            setSidebarOpen(!sidebarOpen);
        }
    };

    if (loading) {
        return <div className="loading">Cargando...</div>;
    }

    return (
        <div className="app">
            {!user ? (
                <Login onLogin={handleLogin} />
            ) : (
                <Dashboard 
                    user={user} 
                    onLogout={handleLogout}
                    sidebarOpen={sidebarOpen}
                    onSidebarHover={handleSidebarHover}
                    onSidebarToggle={handleSidebarToggle}
                />
            )}
        </div>
    );
}

export default App;