import React, { useState, useEffect, useRef, useCallback } from 'react';
import axios from 'axios';



// Componente NotasPopup se mantiene igual...

const HistorialPanel = ({ user }) => {
    const [trabajos, setTrabajos] = useState([]);
    const [loading, setLoading] = useState(true);
    const [loadingMore, setLoadingMore] = useState(false);
    const [error, setError] = useState('');
    
    // Separamos filtros temporales y filtros aplicados
    const [filtrosTemporales, setFiltrosTemporales] = useState({
        fecha: '',
        marca: '',
        modelo: '',
        busqueda: '',
        fecha_inicio: '',
        fecha_fin: ''
    });
    
    const [filtrosAplicados, setFiltrosAplicados] = useState({
        fecha: '',
        marca: '',
        modelo: '',
        busqueda: '',
        fecha_inicio: '',
        fecha_fin: ''
    });
    
    const [opcionesFiltros, setOpcionesFiltros] = useState({
        marcas: [],
        modelos: [],
        fechas: []
    });
    const [eliminando, setEliminando] = useState(null);

    // Nuevos estados para paginación
    const [page, setPage] = useState(1);
    const [hasMore, setHasMore] = useState(true);
    const [totalTrabajos, setTotalTrabajos] = useState(0);

    // Estados para notas
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

    // Cargar trabajos del historial con paginación
    const fetchHistorial = async (filtrosParaAplicar = filtrosAplicados, newPage = 1, append = false) => {
        try {
            if (!append) {
                setLoading(true);
            } else {
                setLoadingMore(true);
            }
            
            setError('');
            const token = localStorage.getItem('token');
            
            const params = new URLSearchParams({
                page: newPage,
                per_page: 10
            });
            
            Object.keys(filtrosParaAplicar).forEach(key => {
                if (filtrosParaAplicar[key]) {
                    params.append(key, filtrosParaAplicar[key]);
                }
            });

            const response = await axios.get(`/api/historial-trabajos?${params}`, {
                headers: {
                    'Authorization': `Bearer ${token}`,
                    'Content-Type': 'application/json'
                }
            });

            if (response.data.success) {
                if (append) {
                    // Agregar a la lista existente
                    setTrabajos(prev => [...prev, ...response.data.data]);
                } else {
                    // Reemplazar la lista completa
                    setTrabajos(response.data.data);
                }
                
                setTotalTrabajos(response.data.total || response.data.data.length);
                
                // Verificar si hay más páginas
                const currentCount = append ? trabajos.length + response.data.data.length : response.data.data.length;
                setHasMore(currentCount < response.data.total && response.data.data.length === 10);
                
                if (newPage === 1) {
                    console.log(`📊 Cargados ${response.data.data.length} de ${response.data.total} trabajos`);
                } else {
                    console.log(`📥 Agregados ${response.data.data.length} trabajos más`);
                }
            } else {
                setError(response.data.error || 'Error al cargar el historial');
            }
        } catch (error) {
            console.error('Error cargando historial:', error);
            setError('Error al cargar el historial de trabajos');
        } finally {
            setLoading(false);
            setLoadingMore(false);
        }
    };

    // Cargar más trabajos
    const loadMoreTrabajos = () => {
        const nextPage = page + 1;
        setPage(nextPage);
        fetchHistorial(filtrosAplicados, nextPage, true);
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
                setTotalTrabajos(prev => prev - 1);
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

    // FUNCIONES PARA MANEJAR NOTAS
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

    // Efecto inicial
    useEffect(() => {
        fetchHistorial();
        fetchOpcionesFiltros();
    }, []);

    // Manejar cambio en filtros TEMPORALES (no aplican inmediatamente)
    const handleFiltroTemporalChange = (key, value) => {
        setFiltrosTemporales(prev => ({
            ...prev,
            [key]: value
        }));
    };

    // Aplicar TODOS los filtros
    const aplicarFiltros = () => {
        setFiltrosAplicados({ ...filtrosTemporales });
        setPage(1); // Resetear a página 1
        fetchHistorial(filtrosTemporales, 1, false);
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
        setFiltrosTemporales(filtrosVacios);
        setFiltrosAplicados(filtrosVacios);
        setPage(1); // Resetear a página 1
        fetchHistorial(filtrosVacios, 1, false);
    };

    // Manejar tecla Enter en el campo de búsqueda
    const handleKeyPress = (e) => {
        if (e.key === 'Enter') {
            aplicarFiltros();
        }
    };

    const renderSubtrabajos = (trabajo) => {
        if (!trabajo.subtrabajos_estado || typeof trabajo.subtrabajos_estado !== 'object') {
            return <div>No hay información de subtrabajos</div>;
        }

        const allSubtrabajos = [];
        
        Object.keys(trabajo.subtrabajos_estado).forEach(subtrabajo => {
            allSubtrabajos.push(subtrabajo);
        });

        const usuariosUnicos = [];
        if (trabajo.subtrabajos_usuario && typeof trabajo.subtrabajos_usuario === 'object') {
            Object.values(trabajo.subtrabajos_usuario).forEach(usuario => {
                if (usuario && !usuariosUnicos.includes(usuario)) {
                    usuariosUnicos.push(usuario);
                }
            });
        }

        return (
            <div className="trabajos-detallados">
                {allSubtrabajos.map((subtrabajo, index) => {
                    const estaCompletado = trabajo.subtrabajos_estado[subtrabajo];
                    const usuarioCompleto = trabajo.subtrabajos_usuario ? trabajo.subtrabajos_usuario[subtrabajo] : null;
                    
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

    // Componente para mostrar el botón de cargar más
    const LoadMoreButton = () => {
        if (!hasMore || trabajos.length === 0) return null;

        return (
            <div className="load-more-container">
                <button 
                    onClick={loadMoreTrabajos}
                    className="btn-load-more"
                    disabled={loadingMore}
                >
                    {loadingMore ? (
                        <>
                            <span className="spinner"></span>
                            Cargando...
                        </>
                    ) : (
                        'Cargar más trabajos'
                    )}
                </button>
            </div>
        );
    };

    if (loading && page === 1) {
        return (
            <div className="loading">
                <div className="spinner"></div>
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
                        onClick={aplicarFiltros}
                        className="btn-primary"
                        disabled={loading}
                    >
                        Aplicar Filtros
                    </button>
                    <button 
                        onClick={limpiarFiltros}
                        className="btn-secondary"
                        disabled={loading}
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
                                onChange={(e) => handleFiltroTemporalChange('busqueda', e.target.value)}
                                onKeyPress={handleKeyPress}
                                className="search-input"
                                disabled={loading}
                            />
                        </div>
                    </div>

                    {/* Filtro por marca */}
                    <div className="form-group">
                        <label>Marca:</label>
                        <select 
                            value={filtrosTemporales.marca}
                            onChange={(e) => handleFiltroTemporalChange('marca', e.target.value)}
                            disabled={loading}
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
                            value={filtrosTemporales.modelo}
                            onChange={(e) => handleFiltroTemporalChange('modelo', e.target.value)}
                            disabled={loading}
                        >
                            <option value="">Todos los modelos</option>
                            {opcionesFiltros.modelos.map(modelo => (
                                <option key={modelo} value={modelo}>{modelo}</option>
                            ))}
                        </select>
                    </div>

                    {/* Rango de fechas */}
                    <div className="form-group">
                        <label>Fecha desde:</label>
                        <input
                            type="date"
                            value={filtrosTemporales.fecha_inicio}
                            onChange={(e) => handleFiltroTemporalChange('fecha_inicio', e.target.value)}
                            disabled={loading}
                        />
                    </div>

                    <div className="form-group">
                        <label>Fecha hasta:</label>
                        <input
                            type="date"
                            value={filtrosTemporales.fecha_fin}
                            onChange={(e) => handleFiltroTemporalChange('fecha_fin', e.target.value)}
                            disabled={loading}
                        />
                    </div>
                </div>
            </div>

            {/* Lista de trabajos */}
            <div className="historial-list">
                {trabajos.length === 0 ? (
                    <div className="no-results">
                        <p>No se encontraron trabajos en el historial</p>
                        {totalTrabajos > 0 && (
                            <p className="no-results-tip">
                                Prueba a cambiar los filtros o limpiarlos para ver todos los trabajos.
                            </p>
                        )}
                    </div>
                ) : (
                    <>
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

                        {/* Botón de cargar más */}
                        <LoadMoreButton />
                    </>
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

const NotasPopup = ({ notas, onNotasChange, onGuardar, onCancelar, guardando, vehiculo }) => {
    return (
        <div className="popup-overlay">
            <div className="popup">
                <div className="popup-content">
                    <h2>Notas del Vehículo</h2>
                    <p><strong>Vehículo:</strong> {vehiculo}</p>
                    
                    <div className="form-group">
                        <label htmlFor="notas">Notas y observaciones:</label>
                        <textarea
                            id="notas"
                            value={notas}
                            onChange={(e) => onNotasChange(e.target.value)}
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
};

export default HistorialPanel;