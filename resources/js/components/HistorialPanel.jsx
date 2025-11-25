import React, { useState, useEffect, useRef, useCallback } from 'react';
import axios from 'axios';

// Componente separado para el popup de notas
const NotasPopup = React.memo(({ 
    notas, 
    onNotasChange, 
    onGuardar, 
    onCancelar, 
    guardando, 
    vehiculo 
}) => {
    const textareaRef = useRef(null);

    // Efecto para enfocar el textarea cuando se monta el componente
    useEffect(() => {
        if (textareaRef.current) {
            textareaRef.current.focus();
            // Colocar el cursor al final del texto
            const length = textareaRef.current.value.length;
            textareaRef.current.setSelectionRange(length, length);
        }
    }, []);

    // Función para manejar el cambio sin causar re-render
    const handleChange = useCallback((e) => {
        onNotasChange(e.target.value);
    }, [onNotasChange]);

    return (
        <div className="popup-overlay">
            <div className="popup">
                <div className="popup-content">
                    <h2>Notas del Vehículo</h2>
                    <p><strong>Vehículo:</strong> {vehiculo}</p>
                    
                    <div className="form-group">
                        <label htmlFor="notas">Notas y observaciones:</label>
                        <textarea
                            ref={textareaRef}
                            id="notas"
                            value={notas}
                            onChange={handleChange}
                            placeholder="Escribe aquí las notas, observaciones, detalles importantes..."
                            rows="8"
                            style={{
                                width: '100%',
                                padding: '12px',
                                border: '1px solid #cbd5e0',
                                borderRadius: '6px',
                                fontSize: '14px',
                                fontFamily: 'inherit',
                                resize: 'vertical'
                            }}
                            onKeyDown={(e) => {
                                if (e.key === 'Enter' && e.ctrlKey) {
                                    e.preventDefault();
                                    onGuardar();
                                }
                            }}
                        />
                    </div>

                    <div className="form-actions">
                        <button 
                            type="button" 
                            onClick={onGuardar}
                            className="btn-success"
                            disabled={guardando}
                        >
                            {guardando ? 'Guardando...' : 'Guardar Notas'}
                        </button>
                        <button 
                            type="button" 
                            onClick={onCancelar}
                            className="btn-cancel"
                            disabled={guardando}
                        >
                            Cancelar
                        </button>
                    </div>
                </div>
            </div>
        </div>
    );
});

const HistorialPanel = ({ user }) => {
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

    // Nuevos estados para notas
    const [showNotasPopup, setShowNotasPopup] = useState(false);
    const [currentNotasTrabajo, setCurrentNotasTrabajo] = useState(null);
    const [notasText, setNotasText] = useState('');
    const [guardandoNotas, setGuardandoNotas] = useState(false);

    // Función para verificar si el usuario es administrador
    const isAdmin = () => {
        return user && user.role === 'admin';
    };

    // Función para formatear hora
    const formatHora = (hora) => {
        if (!hora) return '--:--';
        
        if (hora.includes(':')) {
            const partes = hora.split(':');
            return `${partes[0]}:${partes[1]}`;
        }
        
        return hora;
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

    // FUNCIONES PARA MANEJAR NOTAS - ACTUALIZADAS CON LAS NUEVAS RUTAS
    const handleAbrirNotas = useCallback((trabajo) => {
        setCurrentNotasTrabajo(trabajo);
        setNotasText(trabajo.notas || '');
        setShowNotasPopup(true);
    }, []);

    const handleGuardarNotas = async () => {
        if (!currentNotasTrabajo) return;

        try {
            setGuardandoNotas(true);
            setError('');
            const token = localStorage.getItem('token');
            
            // Usar la nueva ruta PUT para actualizar notas del historial
            const response = await axios.put(`/api/historial-trabajos/${currentNotasTrabajo.id}`, {
                notas: notasText
            }, {
                headers: {
                    'Authorization': `Bearer ${token}`,
                    'Content-Type': 'application/json'
                },
                timeout: 10000
            });

            if (response.data.success) {
                // Actualizar el trabajo en la lista local
                setTrabajos(prev => prev.map(trabajo => 
                    trabajo.id === currentNotasTrabajo.id 
                        ? { ...trabajo, notas: notasText }
                        : trabajo
                ));
                setShowNotasPopup(false);
                setCurrentNotasTrabajo(null);
                
                console.log('✅ Notas guardadas exitosamente en el historial');
            } else {
                setError(response.data.error || 'Error al guardar las notas');
            }
        } catch (error) {
            console.error('Error guardando notas del historial:', error);
            
            // Mensajes de error específicos
            if (error.response?.status === 404) {
                setError('Error: No se encontró el trabajo en el historial.');
            } else if (error.response?.status === 422) {
                setError('Error de validación: ' + (error.response.data.error || 'Datos inválidos'));
            } else if (error.response?.status === 500) {
                setError('Error del servidor: ' + (error.response.data.error || 'Error interno'));
            } else {
                setError('Error al guardar las notas: ' + (error.response?.data?.error || error.message));
            }
        } finally {
            setGuardandoNotas(false);
        }
    };

    const handleCancelarNotas = useCallback(() => {
        setShowNotasPopup(false);
        setCurrentNotasTrabajo(null);
        setNotasText('');
    }, []);

    // Función optimizada para cambiar las notas
    const handleNotasChange = useCallback((value) => {
        setNotasText(value);
    }, []);

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

        console.log('Datos del trabajo en historial:', {
            id: trabajo.id,
            subtrabajos_estado: trabajo.subtrabajos_estado,
            subtrabajos_usuario: trabajo.subtrabajos_usuario,
            vehiculo: `${trabajo.marca} ${trabajo.modelo} ${trabajo.año}`
        });

        const allSubtrabajos = [];
        
        // Obtener todos los subtrabajos del estado
        Object.keys(trabajo.subtrabajos_estado).forEach(subtrabajo => {
            allSubtrabajos.push(subtrabajo);
        });

        // Obtener todos los usuarios únicos que trabajaron en este vehículo
        const usuariosUnicos = [];
        if (trabajo.subtrabajos_usuario && typeof trabajo.subtrabajos_usuario === 'object') {
            Object.values(trabajo.subtrabajos_usuario).forEach(usuario => {
                if (usuario && !usuariosUnicos.includes(usuario)) {
                    usuariosUnicos.push(usuario);
                }
            });
        }

        console.log('Usuarios únicos en este trabajo:', usuariosUnicos);

        return (
            <div className="trabajos-detallados">
                {allSubtrabajos.map((subtrabajo, index) => {
                    const estaCompletado = trabajo.subtrabajos_estado[subtrabajo];
                    const usuarioCompleto = trabajo.subtrabajos_usuario ? trabajo.subtrabajos_usuario[subtrabajo] : null;
                    
                    console.log(`Subtrabajo ${index}:`, {
                        nombre: subtrabajo,
                        completado: estaCompletado,
                        usuario: usuarioCompleto
                    });

                    // Solo mostrar "Completado por:" si hay múltiples usuarios
                    const mostrarUsuario = estaCompletado && usuarioCompleto && usuariosUnicos.length > 1;
                    
                    return (
                        <div key={index} className={`historial-subtrabajo ${estaCompletado ? 'completado' : 'pendiente'}`}>
                            <div className="subtrabajo-estado">
                                {estaCompletado ? '✅' : '❌'}
                            </div>
                            <div className="subtrabajo-info">
                                <span className="subtrabajo-nombre">{subtrabajo}</span>
                                {mostrarUsuario && (
                                    <span className="subtrabajo-usuario">
                                        Completado por: <strong>{usuarioCompleto}</strong>
                                    </span>
                                )}
                                {estaCompletado && !mostrarUsuario && usuarioCompleto && (
                                    <span className="subtrabajo-usuario unico-usuario">
                                    
                                    </span>
                                )}
                                {estaCompletado && !usuarioCompleto && (
                                    <span className="subtrabajo-usuario sin-usuario">
                                        (Usuario no registrado)
                                    </span>
                                )}
                                {!estaCompletado && (
                                    <span className="subtrabajo-usuario pendiente">
                                        Pendiente
                                    </span>
                                )}
                            </div>
                        </div>
                    );
                })}
            </div>
        );
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

                                    {/* Solo mostrar botones de eliminar administradores */}                                   
                                        <div className="delete-button-container">
                                            <button 
                                                className="btn btn-notas"
                                                onClick={() => handleAbrirNotas(trabajo)}
                                                title="Agregar o ver notas"
                                            >
                                                📝
                                            </button>

                                            {isAdmin() && (
                                                <button 
                                                className="btn btn-customD"
                                                onClick={() => handleEliminarTrabajo(trabajo.id)}
                                                disabled={eliminando === trabajo.id}
                                            >
                                                {eliminando === trabajo.id ? 'Eliminando...' : 'Eliminar'}
                                            </button>
                                            )}
                                        </div>                                  
                                </div>
                            </div>
                        ))}
                    </div>
                )}
            </div>

            {/* Popup de notas */}
            {showNotasPopup && (
                <NotasPopup 
                    notas={notasText}
                    onNotasChange={handleNotasChange}
                    onGuardar={handleGuardarNotas}
                    onCancelar={handleCancelarNotas}
                    guardando={guardandoNotas}
                    vehiculo={currentNotasTrabajo ? 
                        `${currentNotasTrabajo.marca} ${currentNotasTrabajo.modelo} ${currentNotasTrabajo.año}` : 
                        ''
                    }
                />
            )}
        </div>
    );
};

export default HistorialPanel;