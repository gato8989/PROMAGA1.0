import React, { useState, useEffect } from 'react';
import axios from 'axios';

const HistorialPanel = ({ user }) => { // Recibir user como prop
    const [trabajos, setTrabajos] = useState([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState('');
    const [filtros, setFiltros] = useState({
        fecha: '',
        marca: '',
        modelo: '',
        busqueda: '',
        fecha_inicio: '',
        fecha_fin: ''
    });
    const [filtrosTemporales, setFiltrosTemporales] = useState({
        busqueda: ''
    });
    const [opcionesFiltros, setOpcionesFiltros] = useState({
        marcas: [],
        modelos: [],
        fechas: []
    });
    const [eliminando, setEliminando] = useState(null);

    // Función para verificar si el usuario es administrador
    const isAdmin = () => {
        return user && (user.role === 'admin' || user.role === 'administrador');
    };

    // Funcion para formatear hora
    const formatHora = (hora) => {
        if (!hora) return '--:--';
        
        // Si la hora viene en formato HH:MM:SS, mostrar solo HH:MM
        if (hora.includes(':')) {
            const partes = hora.split(':');
            return `${partes[0]}:${partes[1]}`;
        }
        
        return hora;
    };

    // Función para verificar si el usuario es técnico
    const isTecnico = () => {
        return user && (user.role === 'tecnico' || user.role === 'técnico');
    };

    // Cargar trabajos del historial
    const fetchHistorial = async (filtrosAplicar = filtros) => {
        try {
            setLoading(true);
            setError('');
            const token = localStorage.getItem('token');
            
            const params = new URLSearchParams();
            Object.keys(filtrosAplicar).forEach(key => {
                if (filtrosAplicar[key]) {
                    params.append(key, filtrosAplicar[key]);
                }
            });

            const response = await axios.get(`/api/historial-trabajos?${params}`, {
                headers: {
                    'Authorization': `Bearer ${token}`,
                    'Content-Type': 'application/json'
                }
            });

            if (response.data.success) {
                setTrabajos(response.data.data);
            } else {
                setError(response.data.error || 'Error al cargar el historial');
            }
        } catch (error) {
            console.error('Error cargando historial:', error);
            setError('Error al cargar el historial de trabajos');
        } finally {
            setLoading(false);
        }
    };

    // Cargar opciones de filtros
    const fetchOpcionesFiltros = async () => {
        try {
            const token = localStorage.getItem('token');
            const response = await axios.get('/api/historial-filtros', {
                headers: {
                    'Authorization': `Bearer ${token}`,
                    'Content-Type': 'application/json'
                }
            });

            if (response.data.success) {
                setOpcionesFiltros(response.data.data);
            } else {
                console.error('Error en respuesta de filtros:', response.data.error);
            }
        } catch (error) {
            console.error('Error cargando opciones de filtros:', error);
        }
    };

    // ELIMINAR TRABAJO DEL HISTORIAL - Solo para administradores
    const handleEliminarTrabajo = async (trabajoId) => {
        // Verificar nuevamente que sea administrador
        if (!isAdmin()) {
            setError('No tienes permisos para eliminar trabajos del historial');
            return;
        }

        if (!window.confirm('¿Estás seguro de que quieres eliminar este trabajo del historial? Esta acción no se puede deshacer.')) {
            return;
        }

        try {
            setEliminando(trabajoId);
            setError('');
            const token = localStorage.getItem('token');

            const response = await axios.delete(`/api/historial-trabajos/${trabajoId}`, {
                headers: {
                    'Authorization': `Bearer ${token}`,
                    'Content-Type': 'application/json'
                }
            });

            if (response.data.success) {
                setTrabajos(prev => prev.filter(trabajo => trabajo.id !== trabajoId));
            } else {
                setError(response.data.error || 'Error al eliminar el trabajo');
            }
        } catch (error) {
            console.error('Error eliminando trabajo:', error);
            setError('Error al eliminar el trabajo del historial');
        } finally {
            setEliminando(null);
        }
    };

    useEffect(() => {
        fetchHistorial();
        fetchOpcionesFiltros();
    }, []);

    // Manejar cambio en filtros (excepto búsqueda)
    const handleFiltroChange = (key, value) => {
        const nuevosFiltros = {
            ...filtros,
            [key]: value
        };
        setFiltros(nuevosFiltros);
        fetchHistorial(nuevosFiltros);
    };

    // Manejar cambio en búsqueda temporal
    const handleBusquedaTemporalChange = (value) => {
        setFiltrosTemporales({
            busqueda: value
        });
    };

    // Aplicar filtro de búsqueda con el botón de lupa
    const handleAplicarBusqueda = () => {
        const nuevosFiltros = {
            ...filtros,
            busqueda: filtrosTemporales.busqueda
        };
        setFiltros(nuevosFiltros);
        fetchHistorial(nuevosFiltros);
    };

    // Limpiar todos los filtros
    const limpiarFiltros = () => {
        const filtrosVacios = {
            fecha: '',
            marca: '',
            modelo: '',
            busqueda: '',
            fecha_inicio: '',
            fecha_fin: ''
        };
        setFiltros(filtrosVacios);
        setFiltrosTemporales({ busqueda: '' });
        fetchHistorial(filtrosVacios);
    };

    // Manejar tecla Enter en el campo de búsqueda
    const handleKeyPress = (e) => {
        if (e.key === 'Enter') {
            handleAplicarBusqueda();
        }
    };

    const renderSubtrabajos = (trabajo) => {
        if (!trabajo.subtrabajos_estado || typeof trabajo.subtrabajos_estado !== 'object') {
            return <div>No hay información de subtrabajos</div>;
        }

        const allSubtrabajos = [];
        
        trabajo.trabajos.forEach(trabajoPrincipal => {
            if (typeof trabajoPrincipal === 'string') {
                switch(trabajoPrincipal) {
                    case "Afinación":
                        allSubtrabajos.push(...Object.keys(trabajo.subtrabajos_estado).filter(key => 
                            key.includes("Cambio de aceite") || key.includes("Filtro") || key.includes("Bujías") ||
                            key.includes("Limpieza") || key.includes("Escaneo") || key.includes("Revisión")
                        ));
                        break;
                    case "Suspensión":
                        allSubtrabajos.push(...Object.keys(trabajo.subtrabajos_estado).filter(key => 
                            key.includes("Horquilla") || key.includes("Buje") || key.includes("Rotula") ||
                            key.includes("Terminal") || key.includes("Balero") || key.includes("Maza")
                        ));
                        break;
                    case "Frenos":
                        allSubtrabajos.push(...Object.keys(trabajo.subtrabajos_estado).filter(key => 
                            key.includes("Balatas") || key.includes("Rectificado") || key.includes("Regresar") ||
                            key.includes("Engrasar") || key.includes("tambores")
                        ));
                        break;
                    default:
                        if (trabajo.subtrabajos_estado[trabajoPrincipal] !== undefined) {
                            allSubtrabajos.push(trabajoPrincipal);
                        }
                }
            }
        });

        return allSubtrabajos.map((subtrabajo, index) => (
            <div key={index} className="historial-subtrabajo">
                <div className={`subtrabajo-estado ${trabajo.subtrabajos_estado[subtrabajo] ? 'completado' : 'pendiente'}`}>
                    {trabajo.subtrabajos_estado[subtrabajo] ? '✅' : '❌'}
                </div>
                <span>{subtrabajo}</span>
            </div>
        ));
    };

    if (loading) {
        return (
            <div className="loading">
                Cargando historial de trabajos...
            </div>
        );
    }

    return (
        <div className="historial-panel">
            <div className="panel-header">
                <h2>Historial de Trabajos Terminados</h2>
                <div className="panel-actions">
                    <button 
                        onClick={limpiarFiltros}
                        className="btn-secondary"
                    >
                        Limpiar Filtros
                    </button>
                </div>
            </div>

            {error && (
                <div className="error-message">
                    <strong>Error:</strong> {error}
                    <button onClick={() => setError('')}>×</button>
                </div>
            )}

            {/* Filtros */}
            <div className="filtros-container">
                <div className="filtros-grid">
                    {/* Búsqueda general con botón de lupa */}
                    <div className="form-group">
                        <label>Buscar:</label>
                        <div className="search-container">
                            <input
                                type="text"
                                placeholder="Buscar por texto"
                                value={filtrosTemporales.busqueda}
                                onChange={(e) => handleBusquedaTemporalChange(e.target.value)}
                                onKeyPress={handleKeyPress}
                                className="search-input"
                            />
                            <button 
                                className="search-button"
                                onClick={handleAplicarBusqueda}
                                title="Buscar"
                            >
                                🔍
                            </button>
                        </div>
                    </div>

                    {/* Filtro por marca */}
                    <div className="form-group">
                        <label>Marca:</label>
                        <select 
                            value={filtros.marca}
                            onChange={(e) => handleFiltroChange('marca', e.target.value)}
                        >
                            <option value="">Todas las marcas</option>
                            {opcionesFiltros.marcas.map(marca => (
                                <option key={marca} value={marca}>{marca}</option>
                            ))}
                        </select>
                    </div>

                    {/* Filtro por modelo */}
                    <div className="form-group">
                        <label>Modelo:</label>
                        <select 
                            value={filtros.modelo}
                            onChange={(e) => handleFiltroChange('modelo', e.target.value)}
                        >
                            <option value="">Todos los modelos</option>
                            {opcionesFiltros.modelos.map(modelo => (
                                <option key={modelo} value={modelo}>{modelo}</option>
                            ))}
                        </select>
                    </div>

                    {/* Filtro por fecha específica */}
                    <div className="form-group">
                        <label>Fecha terminado:</label>
                        <select 
                            value={filtros.fecha}
                            onChange={(e) => handleFiltroChange('fecha', e.target.value)}
                        >
                            <option value="">Todas las fechas</option>
                            {opcionesFiltros.fechas.map(fecha => (
                                <option key={fecha} value={fecha}>{fecha}</option>
                            ))}
                        </select>
                    </div>

                    {/* Rango de fechas */}
                    <div className="form-group">
                        <label>Fecha desde:</label>
                        <input
                            type="date"
                            value={filtros.fecha_inicio}
                            onChange={(e) => handleFiltroChange('fecha_inicio', e.target.value)}
                        />
                    </div>

                    <div className="form-group">
                        <label>Fecha hasta:</label>
                        <input
                            type="date"
                            value={filtros.fecha_fin}
                            onChange={(e) => handleFiltroChange('fecha_fin', e.target.value)}
                        />
                    </div>
                </div>
            </div>

            {/* Lista de trabajos */}
            <div className="historial-list">
                {trabajos.length === 0 ? (
                    <div className="no-results">
                        <p>No se encontraron trabajos en el historial</p>
                    </div>
                ) : (
                    <div className="historial-trabajos-grid">
                        {trabajos.map(trabajo => (
                            <div key={trabajo.id} className="historial-card">
                                <div 
                                    className="card-header"
                                    style={{ backgroundColor: trabajo.color || '#261472' }}
                                >
                                    <h3>{trabajo.marca} {trabajo.modelo} {trabajo.año}</h3>
                                </div>
                                <div className="card-body">
                                    <div className="card-info">
                                        <div className="info-item">
                                            <strong>Fecha ingreso:</strong> 
                                            {trabajo.fecha_ingreso} {trabajo.hora_creacion && `- ${formatHora(trabajo.hora_creacion)}`}
                                        </div>
                                        <div className="info-item">
                                            <strong>Fecha terminado:</strong> 
                                            {trabajo.fecha_terminado} {trabajo.hora_terminado && `- ${formatHora(trabajo.hora_terminado)}`}
                                        </div>
                                        <div className="info-item">
                                            <strong>Terminado por:</strong> {trabajo.usuario_termino}
                                        </div>
                                    </div>

                                    {trabajo.notas && (
                                        <div className="notas-section">
                                            <strong>Notas y observaciones:</strong>
                                            <div className="notas-content">
                                                {trabajo.notas}
                                            </div>
                                        </div>
                                    )}
                                    
                                    <div className="trabajos-list">
                                        <strong>Trabajos realizados:</strong>
                                        {renderSubtrabajos(trabajo)}
                                    </div>

                                    {/* Solo mostrar botón de eliminar para administradores */}
                                    {isAdmin() && (
                                        <div className="card-actions">
                                            <button 
                                                className="btn btn-danger btn-sm"
                                                onClick={() => handleEliminarTrabajo(trabajo.id)}
                                                disabled={eliminando === trabajo.id}
                                            >
                                                {eliminando === trabajo.id ? 'Eliminando...' : 'Eliminar del Historial'}
                                            </button>
                                        </div>
                                    )}
                                </div>
                            </div>
                        ))}
                    </div>
                )}
            </div>
        </div>
    );
};

export default HistorialPanel;