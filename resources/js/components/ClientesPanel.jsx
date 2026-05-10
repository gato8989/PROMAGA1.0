import React, { useState, useEffect } from 'react';
import axios from 'axios';

const ClientesPanel = ({ user }) => {
    const [clientes, setClientes] = useState([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState('');
    const [success, setSuccess] = useState('');
    const [showForm, setShowForm] = useState(false);
    const [editMode, setEditMode] = useState(false);
    const [editClienteId, setEditClienteId] = useState(null);
    const [formData, setFormData] = useState({ nombre: '', telefono: '+52 ', historial_trabajo_id: '' });
    const [saving, setSaving] = useState(false);
    const [busquedaTrabajo, setBusquedaTrabajo] = useState('');
    const [trabajosEncontrados, setTrabajosEncontrados] = useState([]);
    const [buscandoTrabajos, setBuscandoTrabajos] = useState(false);
    const [whatsappConnected, setWhatsappConnected] = useState(false);
    const [qrCode, setQrCode] = useState(null);
    const [showQr, setShowQr] = useState(false);
    const [checkingWhatsapp, setCheckingWhatsapp] = useState(false);
    const [disconnecting, setDisconnecting] = useState(false);
    const [enviando, setEnviando] = useState({});
    const [filtroActivo, setFiltroActivo] = useState('todos');
    const [busquedaGlobal, setBusquedaGlobal] = useState('');

    const token = localStorage.getItem('token');
    const headers = { 'Authorization': `Bearer ${token}`, 'Content-Type': 'application/json' };

    useEffect(() => { fetchClientes(); checkWhatsappStatus(); }, []);

    // Nuevo efecto: Cuando se abre el formulario, cargar los últimos 10 trabajos automáticamente
    useEffect(() => {
        if (showForm && !busquedaTrabajo && !editMode) {
            cargarUltimosTrabajos();
        }
    }, [showForm]);

    const fetchClientes = async () => {
        try {
            setLoading(true);
            const res = await axios.get('/api/clientes', { headers });
            if (res.data.success) setClientes(res.data.data);
        } catch (err) { setError('Error al cargar clientes'); }
        finally { setLoading(false); }
    };

    const checkWhatsappStatus = async () => {
        try {
            setCheckingWhatsapp(true);
            const res = await axios.get('/api/clientes/whatsapp-status', { headers });
            if (res.data.success) {
                setWhatsappConnected(res.data.connected);
                if (!res.data.connected && res.data.qrcode) setQrCode(res.data.qrcode);
            }
        } catch (err) { console.error(err); }
        finally { setCheckingWhatsapp(false); }
    };

    const handleDisconnectWhatsapp = async () => {
        if (!window.confirm('¿Desconectar WhatsApp? Deberás escanear el QR nuevamente.')) return;
        try {
            setDisconnecting(true);
            await axios.post('/api/clientes/whatsapp-logout', {}, { headers });
            setWhatsappConnected(false);
            setQrCode(null);
            setSuccess('WhatsApp desconectado. Escanea el QR para reconectar.');
            
            setTimeout(async () => {
                await checkWhatsappStatus();
            }, 3000);
            
            setTimeout(() => setSuccess(''), 5000);
        } catch (err) {
            console.error('Error desconectando:', err);
            setWhatsappConnected(false);
            setQrCode(null);
            setSuccess('WhatsApp desconectado (forzado)');
            setTimeout(async () => {
                await checkWhatsappStatus();
            }, 3000);
        } finally {
            setDisconnecting(false);
        }
    };

    // Nueva función para cargar los últimos trabajos
    const cargarUltimosTrabajos = async () => {
        try {
            setBuscandoTrabajos(true);
            // Llamar sin término de búsqueda para obtener los últimos 10
            const res = await axios.get(`/api/clientes/buscar-trabajos`, { headers });
            if (res.data.success) {
                setTrabajosEncontrados(res.data.data);
            }
        } catch (err) {
            console.error('Error cargando últimos trabajos:', err);
        } finally {
            setBuscandoTrabajos(false);
        }
    };

    const buscarTrabajos = async (termino) => {
        setBusquedaTrabajo(termino);
        if (termino.length < 1) { 
            // Si el término está vacío, cargar los últimos 10 trabajos
            setTrabajosEncontrados([]);
            if (showForm) {
                await cargarUltimosTrabajos();
            }
            return; 
        }
        try {
            setBuscandoTrabajos(true);
            const res = await axios.get(`/api/clientes/buscar-trabajos?busqueda=${encodeURIComponent(termino)}`, { headers });
            if (res.data.success) setTrabajosEncontrados(res.data.data);
        } catch (err) { 
            console.error(err); 
            setError('Error al buscar trabajos');
            setTimeout(() => setError(''), 3000);
        } finally {
            setBuscandoTrabajos(false);
        }
    };

    const seleccionarTrabajo = (trabajo) => {
        setFormData(prev => ({ ...prev, historial_trabajo_id: trabajo.id }));
        setBusquedaTrabajo(`${trabajo.marca} ${trabajo.modelo} ${trabajo.año}`);
        setTrabajosEncontrados([]);
    };

    const limpiarTrabajoSeleccionado = () => {
        setFormData(prev => ({ ...prev, historial_trabajo_id: '' }));
        setBusquedaTrabajo('');
        // Recargar los últimos trabajos después de limpiar
        if (showForm) {
            cargarUltimosTrabajos();
        }
    };

    const handleSubmit = async (e) => {
        e.preventDefault();
        if (!formData.nombre.trim() || !formData.telefono.trim()) { setError('Nombre y teléfono son requeridos'); return; }
        
        // Limpiar el teléfono: eliminar espacios y asegurar formato
        let telefonoLimpio = formData.telefono.trim();
        // Si no empieza con +, agregar +52 (por si el usuario borró el prefijo)
        if (!telefonoLimpio.startsWith('+')) {
            telefonoLimpio = '+52 ' + telefonoLimpio.replace(/\s/g, '');
        }
        
        const datosEnvio = {
            ...formData,
            telefono: telefonoLimpio
        };
        
        try {
            setSaving(true); setError('');
            let res = editMode
                ? await axios.put(`/api/clientes/${editClienteId}`, datosEnvio, { headers })
                : await axios.post('/api/clientes', datosEnvio, { headers });
            if (res.data.success) { setSuccess(editMode ? 'Cliente actualizado' : 'Cliente registrado'); resetForm(); fetchClientes(); setTimeout(() => setSuccess(''), 3000); }
        } catch (err) { setError(err.response?.data?.error || 'Error'); }
        finally { setSaving(false); }
    };

    const editarCliente = (cliente) => {
        setEditMode(true); setEditClienteId(cliente.id);
        setFormData({ 
            nombre: cliente.nombre, 
            telefono: cliente.telefono, 
            historial_trabajo_id: cliente.historial_trabajo_id || '' 
        });
        if (cliente.historial_trabajo) {
            const t = cliente.historial_trabajo;
            setBusquedaTrabajo(`${t.marca} ${t.modelo} ${t.año} (${t.fecha_terminado})`);
        }
        setShowForm(true);
    };

    const resetForm = () => {
        setShowForm(false); setEditMode(false); setEditClienteId(null);
        setFormData({ nombre: '', telefono: '+52 ', historial_trabajo_id: '' });
        setBusquedaTrabajo(''); setTrabajosEncontrados([]);
    };

    const handleEnviarRecordatorio = async (clienteId) => {
        const key = `rec_${clienteId}`;
        try {
            setEnviando(prev => ({ ...prev, [key]: true })); 
            setError('');
            const res = await axios.post(`/api/clientes/${clienteId}/recordatorio`, {}, { headers });
            if (res.data.success) { 
                setSuccess('✅ Recordatorio enviado'); 
                await fetchClientes();
                setTimeout(() => setSuccess(''), 3000); 
            } else { 
                setError(res.data.error || 'No se puede enviar recordatorio aún');
                setTimeout(() => setError(''), 3000);
            }
        } catch (err) { 
            setError(err.response?.data?.error || 'Error al enviar recordatorio'); 
            setTimeout(() => setError(''), 3000);
        } finally { 
            setEnviando(prev => ({ ...prev, [key]: false })); 
        }
    };

    const handleEnviarFinalizacion = async (clienteId) => {
        const key = `fin_${clienteId}`;
        try {
            setEnviando(prev => ({ ...prev, [key]: true })); setError('');
            const res = await axios.post(`/api/clientes/${clienteId}/finalizacion`, {}, { headers });
            if (res.data.success) { setSuccess('✅ Finalización enviada'); fetchClientes(); setTimeout(() => setSuccess(''), 3000); }
            else { setError(res.data.error); }
        } catch (err) { setError(err.response?.data?.error || 'Error'); }
        finally { setEnviando(prev => ({ ...prev, [key]: false })); }
    };

    const handleGenerarGarantia = async (clienteId) => {
        const key = `gar_${clienteId}`;
        try {
            setEnviando(prev => ({ ...prev, [key]: true }));
            setError('');
            const res = await axios.post(`/api/clientes/${clienteId}/garantia`, {}, { headers });
            
            if (res.data.success) {
                setSuccess('✅ Garantía enviada por WhatsApp');
                fetchClientes();
                
                if (res.data.pdf_url) {
                    setTimeout(() => {
                        window.open(res.data.pdf_url, '_blank');
                    }, 500);
                }
                
                setTimeout(() => setSuccess(''), 3000);
            } else {
                setError(res.data.error || 'Error al generar garantía');
            }
        } catch (err) {
            setError(err.response?.data?.error || 'Error al generar garantía');
        } finally {
            setEnviando(prev => ({ ...prev, [key]: false }));
        }
    };

    const handleEnviarRecomendacion = async (clienteId) => {
        const key = `recom_${clienteId}`;
        try {
            setEnviando(prev => ({ ...prev, [key]: true })); 
            setError('');
            const res = await axios.post(`/api/clientes/${clienteId}/recomendacion`, {}, { headers });
            if (res.data.success) { 
                setSuccess('⭐ Recomendación enviada'); 
                await fetchClientes();
                setTimeout(() => setSuccess(''), 3000); 
            } else { 
                setError(res.data.error || 'No se puede enviar recomendación aún');
                setTimeout(() => setError(''), 3000);
            }
        } catch (err) { 
            console.error('Error en recomendación:', err);
            setError(err.response?.data?.error || 'Error al enviar recomendación'); 
            setTimeout(() => setError(''), 3000);
        } finally { 
            setEnviando(prev => ({ ...prev, [key]: false })); 
        }
    };

    const handleEliminar = async (clienteId) => {
        if (!window.confirm('¿Eliminar este cliente?')) return;
        try { await axios.delete(`/api/clientes/${clienteId}`, { headers }); setSuccess('Cliente eliminado'); fetchClientes(); setTimeout(() => setSuccess(''), 3000); }
        catch (err) { setError('Error al eliminar'); }
    };

    const getRecordatorioInfo = (cliente) => {
        if (!cliente.ultima_visita) return { texto: 'Sin registro', clase: 'client-badge-gray', dias: null, puede: false };
        
        // Usar el valor que viene del backend
        const puede = cliente.puede_recordatorio === true;
        const dias = cliente.dias_para_recordatorio;
        
        if (dias === null || dias === undefined) {
            return { texto: '--', clase: 'client-badge-gray', dias: null, puede: false };
        }
        
        if (dias <= 0) {
            return { texto: '¡Listo!', clase: 'client-badge-green', dias: Math.abs(dias), puede: true };
        }
        
        if (dias <= 30) {
            return { texto: `${dias} días`, clase: 'client-badge-yellow', dias, puede: false };
        }
        
        return { texto: `${dias} días`, clase: 'client-badge-red', dias, puede: false };
    };

    const getRecomendacionInfo = (cliente) => {
        // Si ya se envió, mostrar checkmark y botón deshabilitado
        if (cliente.recomendacion_enviada === true) {
            return { puede: false, texto: 'Enviada', clase: 'client-badge-gray', dias: null };
        }
        
        // Si no tiene trabajo asignado
        if (!cliente.historial_trabajo) {
            return { puede: false, texto: 'Sin vehículo', clase: 'client-badge-gray', dias: null };
        }
        
        // Usar el valor que viene del backend
        const puede = cliente.puede_recomendacion === true;
        const dias = cliente.dias_para_recomendacion;
        
        // Si el backend dice que puede (puede_recomendacion = true)
        if (puede) {
            return { puede: true, texto: '¡Listo!', clase: 'client-badge-green', dias: 0 };
        }
        
        // Si no puede, mostrar los días restantes
        if (dias !== null && dias !== undefined && dias > 0) {
            return { puede: false, texto: `${dias} días`, clase: 'client-badge-yellow', dias: dias };
        }
        
        // Caso por defecto (no disponible)
        return { puede: false, texto: 'Esperando', clase: 'client-badge-gray', dias: null };
    };

    const clientesFiltrados = clientes.filter(cliente => {
        // Primero aplicar búsqueda global (nombre o teléfono)
        if (busquedaGlobal.trim() !== '') {
            const termino = busquedaGlobal.toLowerCase();
            const nombreMatch = cliente.nombre.toLowerCase().includes(termino);
            const telefonoMatch = cliente.telefono.toLowerCase().includes(termino);
            if (!nombreMatch && !telefonoMatch) return false;
        }
        
        // Luego aplicar filtro por tipo de botón
        if (filtroActivo === 'todos') return true;
        if (filtroActivo === 'recordatorio') {
            const info = getRecordatorioInfo(cliente);
            return info.puede === true;
        }
        if (filtroActivo === 'recomendacion') {
            const info = getRecomendacionInfo(cliente);
            // SOLO mostrar si puede === true (es decir, cuando realmente está disponible)
            return info.puede === true;
        }
        return true;
    });

    if (loading) {
        return (
            <div className="client-loading">
                <div className="client-spinner"></div>
                <p>Cargando clientes...</p>
            </div>
        );
    }

    return (
        <div className="client-container">
            {/* HEADER */}
            <div className="client-header">
                <h2 className="dash-title">Gestión de Clientes</h2>
                <div className="client-header-actions">
                    <button onClick={() => { setShowQr(!showQr); if (!showQr) checkWhatsappStatus(); }}
                        className={`client-btn-status ${whatsappConnected ? 'client-status-connected' : 'client-status-disconnected'}`}>
                        📱 {whatsappConnected ? 'Conectado' : 'Desconectado'}
                    </button>
                    <button onClick={() => { resetForm(); setShowForm(!showForm); }} className="client-btn-primary">
                        {showForm ? 'Cancelar' : '+ Nuevo Cliente'}
                    </button>
                </div>
            </div>

            {/* MENSAJES */}
            {error && <div className="client-alert client-alert-error">{error}<button onClick={() => setError('')} className="client-alert-close">×</button></div>}
            {success && <div className="client-alert client-alert-success">{success}</div>}

            {/* QR */}
            {showQr && (
                <div className="client-qr-section">
                    <h3>📱 {whatsappConnected ? 'WhatsApp Conectado' : 'Vincular WhatsApp'}</h3>
                    {checkingWhatsapp ? <p>Verificando...</p> : whatsappConnected ? (
                        <>
                            <p className="client-text-green">✅ Conectado y listo.</p>
                            <button onClick={handleDisconnectWhatsapp} className="client-btn-danger-outline" disabled={disconnecting}>
                                {disconnecting ? 'Desconectando...' : '🔌 Desconectar WhatsApp'}
                            </button>
                        </>
                    ) : qrCode ? (
                        <>
                            <p>Escanea este QR con WhatsApp</p>
                            <img src={qrCode} alt="QR WhatsApp" className="client-qr-image" />
                            <p className="client-qr-help">WhatsApp → Dispositivos vinculados → Vincular dispositivo</p>
                        </>
                    ) : <p>Cargando QR...</p>}
                    <button onClick={() => setShowQr(false)} className="client-btn-secondary">Cerrar</button>
                </div>
            )}

            {/* FORMULARIO */}
            {showForm && (
                <div className="client-form-card">
                    <h3>{editMode ? 'Editar Cliente' : 'Registrar Nuevo Cliente'}</h3>
                    <form onSubmit={handleSubmit}>
                        <div className="client-form-row">
                            <div className="client-form-group">
                                <label>Nombre *</label>
                                <input type="text" value={formData.nombre} onChange={(e) => setFormData(prev => ({ ...prev, nombre: e.target.value }))} placeholder="Nombre completo" required />
                            </div>
                            <div className="client-form-group">
                                <label>Teléfono *</label>
                                <input 
                                    type="tel" 
                                    value={formData.telefono} 
                                    onChange={(e) => {
                                        let valor = e.target.value;
                                        // Si el usuario borra completamente, volver a poner +52
                                        if (valor.trim() === '') {
                                            valor = '+52 ';
                                        }
                                        setFormData(prev => ({ ...prev, telefono: valor }));
                                    }} 
                                    placeholder="+52 1234567890" 
                                    required 
                                />
                                <small className="client-input-hint">📱 Formato: +52 seguido de 10 dígitos (ej: +52 5551234567)</small>
                            </div>
                        </div>
                        <div className="client-form-group">
                            <label>Asignar vehículo del historial</label>
                            <div className="client-search-box">
                                <input 
                                    type="text" 
                                    value={busquedaTrabajo} 
                                    onChange={(e) => buscarTrabajos(e.target.value)} 
                                    placeholder="Buscar por marca, modelo o año... (dejar vacío para ver últimos 10)" 
                                />
                                {busquedaTrabajo && formData.historial_trabajo_id && (
                                    <button type="button" onClick={limpiarTrabajoSeleccionado} className="client-btn-clear-input">×</button>
                                )}
                            </div>
                            {buscandoTrabajos && <small className="client-search-loading">🔍 Buscando trabajos...</small>}
                            {!buscandoTrabajos && trabajosEncontrados.length > 0 && !formData.historial_trabajo_id && (
                                <div className="client-search-results-container">
                                    <div className="client-search-results-header">
                                        <span>📋 {!busquedaTrabajo ? 'Últimos trabajos finalizados' : 'Resultados encontrados'}</span>
                                        {!busquedaTrabajo && <small>(más recientes primero)</small>}
                                    </div>
                                    <ul className="client-search-results">
                                        {trabajosEncontrados.map(t => (
                                            <li key={t.id} onClick={() => seleccionarTrabajo(t)} className="client-search-result-item">
                                                <span className="client-color-dot" style={{ backgroundColor: t.color || '#261472' }}></span>
                                                <span className="client-vehicle-info">
                                                    <strong>{t.marca} {t.modelo}</strong> ({t.año})
                                                </span>
                                                <span className="client-vehicle-date">{t.fecha_terminado}</span>
                                            </li>
                                        ))}
                                    </ul>
                                </div>
                            )}
                            {!buscandoTrabajos && !trabajosEncontrados.length && !formData.historial_trabajo_id && showForm && (
                                <div className="client-search-empty">
                                    <small>⚠️ No hay trabajos finalizados disponibles</small>
                                </div>
                            )}
                        </div>
                        <div className="client-form-actions">
                            <button type="submit" className="client-btn-success" disabled={saving}>{saving ? 'Guardando...' : editMode ? 'Actualizar' : 'Registrar'}</button>
                            <button type="button" onClick={resetForm} className="client-btn-cancel">Cancelar</button>
                        </div>
                    </form>
                </div>
            )}

            {/* BARRA DE BÚSQUEDA GLOBAL */}
            <div className="client-search-bar">
                <div className="client-search-input-wrapper">
                    <span className="client-search-icon">🔍</span>
                    <input 
                        type="text"
                        className="client-search-input"
                        placeholder="Buscar por nombre o teléfono..."
                        value={busquedaGlobal}
                        onChange={(e) => setBusquedaGlobal(e.target.value)}
                    />
                    {busquedaGlobal && (
                        <button 
                            className="client-search-clear"
                            onClick={() => setBusquedaGlobal('')}
                            title="Limpiar búsqueda"
                        >
                            ✕
                        </button>
                    )}
                </div>
            </div>

            {/* FILTROS */}
            <div className="client-filters">
                <button onClick={() => setFiltroActivo('todos')} className={`client-btn-filter ${filtroActivo === 'todos' ? 'client-filter-active' : ''}`}>
                    📋 Todos ({clientes.filter(c => {
                        if (busquedaGlobal.trim() !== '') {
                            const termino = busquedaGlobal.toLowerCase();
                            return c.nombre.toLowerCase().includes(termino) || c.telefono.toLowerCase().includes(termino);
                        }
                        return true;
                    }).length})
                </button>
                <button onClick={() => setFiltroActivo('recordatorio')} className={`client-btn-filter ${filtroActivo === 'recordatorio' ? 'client-filter-active' : ''}`}>
                    🔧 Recordatorio ({clientes.filter(c => {
                        if (busquedaGlobal.trim() !== '') {
                            const termino = busquedaGlobal.toLowerCase();
                            if (!c.nombre.toLowerCase().includes(termino) && !c.telefono.toLowerCase().includes(termino)) return false;
                        }
                        return c.puede_recordatorio === true;
                    }).length})
                </button>
                <button onClick={() => setFiltroActivo('recomendacion')} className={`client-btn-filter ${filtroActivo === 'recomendacion' ? 'client-filter-active' : ''}`}>
                    ⭐ Recomendación ({clientes.filter(c => {
                        if (busquedaGlobal.trim() !== '') {
                            const termino = busquedaGlobal.toLowerCase();
                            if (!c.nombre.toLowerCase().includes(termino) && !c.telefono.toLowerCase().includes(termino)) return false;
                        }
                        // SOLO contar cuando puede_recomendacion es true Y no ha sido enviada
                        return c.puede_recomendacion === true && c.recomendacion_enviada !== true;
                    }).length})
                </button>
            </div>

            {/* TABLA */}
            <div className="client-table-wrapper">
                {clientesFiltrados.length === 0 ? (
                    <div className="client-empty">
                        <p>No hay clientes {filtroActivo !== 'todos' ? 'con este filtro' : 'registrados'}</p>
                        <p>{busquedaGlobal && 'Intenta con otra búsqueda o '}{filtroActivo !== 'todos' ? 'Prueba con otro filtro o ' : ''}Usa el botón "+ Nuevo Cliente" para empezar</p>
                    </div>
                ) : (
                    <table className="client-table">
                        <thead>
                            <tr>
                                <th>Nombre</th>
                                <th>Teléfono</th>
                                <th>Vehículo</th>
                                <th>Recordatorio</th>
                                <th>Finalización</th>
                                <th>Garantía</th>
                                <th>Recomendación</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            {clientesFiltrados.map(cliente => {
                                const infoRecordatorio = getRecordatorioInfo(cliente);
                                const infoRecomendacion = getRecomendacionInfo(cliente);
                                return (
                                    <tr key={cliente.id} className={infoRecordatorio.puede ? 'client-row-highlight' : ''}>
                                        <td>
                                            <div className="client-name-cell">
                                                <div className="client-avatar-small">{cliente.nombre.charAt(0).toUpperCase()}</div>
                                                <span>{cliente.nombre}</span>
                                            </div>
                                        </td>
                                        <td><span className="client-phone">{cliente.telefono}</span></td>
                                        <td>
                                            {cliente.historial_trabajo ? (
                                                <div className="client-vehicle-cell">
                                                    <span className="client-vehicle-color" style={{ backgroundColor: cliente.historial_trabajo.color || '#261472' }}></span>
                                                    <span>{cliente.historial_trabajo.marca} {cliente.historial_trabajo.modelo} {cliente.historial_trabajo.año}</span>
                                                </div>
                                            ) : <span className="client-text-gray">Sin asignar</span>}
                                        </td>
                                        <td>
                                            <div className="client-reminder-cell">
                                                <span className={`client-badge ${infoRecordatorio.clase}`}>{infoRecordatorio.texto}</span>
                                                <button 
                                                    onClick={() => handleEnviarRecordatorio(cliente.id)}
                                                    disabled={!infoRecordatorio.puede || enviando[`rec_${cliente.id}`]}
                                                    className="client-btn-action client-btn-blue"
                                                    title={infoRecordatorio.puede ? "Enviar recordatorio" : `Disponible en ${infoRecordatorio.texto}`}>
                                                    {enviando[`rec_${cliente.id}`] ? '⏳' : '🔧'}
                                                </button>
                                            </div>
                                        </td>
                                        <td>
                                            <div className="client-action-cell">
                                                <button onClick={() => handleEnviarFinalizacion(cliente.id)}
                                                    disabled={!cliente.historial_trabajo || enviando[`fin_${cliente.id}`]}
                                                    className={`client-btn-action ${cliente.historial_trabajo ? 'client-btn-blue' : 'client-btn-disabled'}`}>
                                                    {enviando[`fin_${cliente.id}`] ? '⏳' : '✅'}
                                                </button>
                                                {cliente.finalizacion_enviada && <span className="client-sent-indicator">✓</span>}
                                            </div>
                                        </td>
                                        <td>
                                            <div className="client-action-cell">
                                                <button onClick={() => handleGenerarGarantia(cliente.id)}
                                                    disabled={!cliente.historial_trabajo || enviando[`gar_${cliente.id}`]}
                                                    className={`client-btn-action ${cliente.historial_trabajo ? 'client-btn-purple' : 'client-btn-disabled'}`}>
                                                    {enviando[`gar_${cliente.id}`] ? '⏳' : '📄'}
                                                </button>
                                                {cliente.garantia_enviada && <span className="client-sent-indicator">✓</span>}
                                            </div>
                                        </td>
                                        <td>
                                            <div className="client-action-cell">
                                                <button 
                                                    onClick={() => handleEnviarRecomendacion(cliente.id)}
                                                    disabled={!infoRecomendacion.puede || enviando[`recom_${cliente.id}`]}
                                                    className={`client-btn-action ${infoRecomendacion.puede ? 'client-btn-orange' : 'client-btn-disabled'}`}
                                                    title={infoRecomendacion.puede ? "Enviar recomendación" : infoRecomendacion.texto}>
                                                    {enviando[`recom_${cliente.id}`] ? '⏳' : '⭐'}
                                                </button>
                                                {cliente.recomendacion_enviada === true && <span className="client-sent-indicator">✓</span>}
                                                {/* Mostrar badge de estado si no está disponible */}
                                                {!infoRecomendacion.puede && cliente.recomendacion_enviada !== true && (
                                                    <span className={`client-badge ${infoRecomendacion.clase}`} style={{ marginLeft: '0.5rem', fontSize: '0.7rem' }}>
                                                        {infoRecomendacion.texto}
                                                    </span>
                                                )}
                                            </div>
                                        </td>
                                        <td>
                                            <div className="client-row-actions">
                                                <button onClick={() => editarCliente(cliente)} className="client-btn-icon" title="Editar">✏️</button>
                                                <button onClick={() => handleEliminar(cliente.id)} className="client-btn-icon client-btn-danger" title="Eliminar">🗑️</button>
                                            </div>
                                        </td>
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                )}
            </div>
        </div>
    );
};

export default ClientesPanel;