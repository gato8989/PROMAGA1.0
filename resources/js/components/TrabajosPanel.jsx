import React, { useState, useEffect, useRef } from 'react';
import axios from 'axios';

const TrabajosPanel = ({ user }) => {
    // Estados para datos dinámicos de NHTSA
    const [marcasData, setMarcasData] = useState([]);
    const [añosData, setAñosData] = useState([]);
    const [modelosData, setModelosData] = useState([]);
    const [loadingMarcas, setLoadingMarcas] = useState(false);
    const [loadingAños, setLoadingAños] = useState(false);
    const [loadingModelos, setLoadingModelos] = useState(false);
    const [apiStatus, setApiStatus] = useState('checking');

    // Estados principales
    const [sections, setSections] = useState(Array(9).fill(null));
    const [showPopup, setShowPopup] = useState(false);
    const [currentSection, setCurrentSection] = useState(null);
    const [formData, setFormData] = useState({
        marca: '',
        año: '',
        modelo: '',
        trabajos: ['']
    });
    const [loading, setLoading] = useState(true);

    // Estados para notas
    const [showNotasPopup, setShowNotasPopup] = useState(false);
    const [currentNotasTrabajo, setCurrentNotasTrabajo] = useState(null);
    const [notasText, setNotasText] = useState('');
    const [guardandoNotas, setGuardandoNotas] = useState(false);

    // Estados para edición
    const [showEditarPopup, setShowEditarPopup] = useState(false);
    const [trabajoEditando, setTrabajoEditando] = useState(null);
    const [formDataEditar, setFormDataEditar] = useState({
        marca: '',
        modelo: '',
        año: '',
        trabajos: [],
        color: '#261472',
        subtrabajos_seleccionados: {}
    });
    const [guardandoEdicion, setGuardandoEdicion] = useState(false);

    // Estados para polling - OPTIMIZADOS
    const [pollingStatus, setPollingStatus] = useState('inactive');
    const pollingRef = useRef(null);
    const lastHashRef = useRef(null);
    const [forceRefresh, setForceRefresh] = useState(0);
    const errorCountRef = useRef(0);
    const consecutiveUpdatesRef = useRef(0);

    // Estados para los trabajos activos en el FORMULARIO ACTUAL
    const [trabajosActivosForm, setTrabajosActivosForm] = useState({
        trabajosActivosAfinacion: [],
        trabajosActivosSuspension: [],
        trabajosActivosFrenos: []
    });

    // Estados para los trabajos disponibles (estos no cambian)
    const [trabajosData, setTrabajosData] = useState({
        trabajosAfinacion: [
            "Cambio de aceite", "Filtro de aceite", "Etiqueta", "Bujías", "Filtro de aire", 
            "Limpieza de cuerpo de aceleración", "Limpieza de inyectores", "Filtro de gasolina", 
            "Escaneo", "Revisión de niveles","Reiniciar Servicio","Revisión de suspensión"
        ],
        trabajosSuspension: [
            "Amortiguador Delantero Derecho", "Amortiguador Delantero Izquierdo", "Amortiguador Trasero Derecho", "Amortiguador Trasero Izquierdo", 
            "Balero delantero derecho", "Balero delantero izquierdo", "Balero trasero derecho", "Baleto trasero izquierdo", "Base Amortiguador Delantera Derecha", "Base Amortiguador Delantera Izquierda", 
            "Base Amortiguador Trasera Derecha", "Base Amortiguador Trasera Izquierda", "Bieleta Derecha", "Bieleta izquierda", "Bujes chicos", "Bujes grandes", 
            "Flecha derecha", "Flecha izquierda", "Gomas de la barra delanterar", "Gomas de la barra traseras", "Horquilla derecha", "Horquilla izquierda", 
            "Junta homocinética derecha", "Junta homocinética izquierda", "Maza delantera derecha", "Maza delantera izquierda", "Maza trasera derecha", 
            "Maza trasera izquierda", "Rotula derecha", "Rotula izquierda", "Terminal derecha", "Terminal izquierda", "Tornillo estabilizador derecho", 
            "Tornillo estabilizador izquierdo"
        ],
        trabajosFrenos: [
            "Balatas delanteras", "Rectificado de discos", "Regresar pistones", 
            "Engrasar pernos", "Balatas traseras", "Rectificado de tambores"
        ]
    });

    // Efecto principal
    useEffect(() => {
        console.log('🚀 Iniciando TrabajosPanel...');
        checkApiStatus();
        fetchMarcas();
        fetchTrabajos();
        startPolling();

        return () => {
            if (pollingRef.current) {
                clearInterval(pollingRef.current);
                console.log('🛑 Polling detenido');
            }
        };
    }, []);

    // Efecto para forzar actualizaciones
    useEffect(() => {
        if (forceRefresh > 0) {
            console.log('🔄 Forzando actualización por cambio local...');
            fetchTrabajos();
        }
    }, [forceRefresh]);

    // Polling optimizado - CON INTERVALO ADAPTATIVO
    const startPolling = () => {
        console.log('🟢 Iniciando polling adaptativo...');
        setPollingStatus('active');

        pollingRef.current = setInterval(() => {
            checkForUpdates();
        }, getPollingInterval());
    };

    // Intervalo de polling adaptativo
    const getPollingInterval = () => {
        // Si hay muchos cambios recientes, verificar más frecuentemente
        if (consecutiveUpdatesRef.current > 2) {
            return 2000; // 2 segundos durante actividad alta
        }
        
        // Si hay errores, aumentar el intervalo para dar tiempo al servidor
        if (errorCountRef.current > 0) {
            return 10000; // 10 segundos si hay errores
        }
        
        return 5000; // 5 segundos por defecto (reducido de 3 segundos)
    };

    // Función para verificar actualizaciones - OPTIMIZADA
    const checkForUpdates = async () => {
        // Si ya está actualizando, saltar esta verificación
        if (pollingStatus === 'updating') {
            console.log('⏭️ Saltando verificación - ya se está actualizando');
            return;
        }

        try {
            console.log('🔍 Verificando actualizaciones...');
            
            const token = localStorage.getItem('token');
            const response = await axios.get(`/api/trabajos/last-update?t=${Date.now()}`, {
                headers: {
                    'Authorization': `Bearer ${token}`,
                    'Content-Type': 'application/json'
                },
                timeout: 8000 // Timeout reducido
            });

            if (response.data.success) {
                const serverStateHash = response.data.state_hash;
                const currentStateHash = lastHashRef.current;
                
                console.log('🔍 State Hash - Servidor:', serverStateHash);
                console.log('🔍 State Hash - Local:', currentStateHash);
                
                if (currentStateHash === null) {
                    // Primera vez - inicializar
                    console.log('📅 Inicializando state hash:', serverStateHash);
                    lastHashRef.current = serverStateHash;
                    setPollingStatus('active');
                    errorCountRef.current = 0; // Resetear contador de errores
                } else if (serverStateHash !== currentStateHash) {
                    // ¡HAY CAMBIOS!
                    console.log('🔄 CAMBIOS DETECTADOS! Actualizando...');
                    setPollingStatus('updating');
                    
                    consecutiveUpdatesRef.current++;
                    
                    // Forzar recarga de trabajos
                    await fetchTrabajos();
                    
                    // Actualizar hash local
                    lastHashRef.current = serverStateHash;
                    setPollingStatus('active');
                    console.log('✅ Actualización completada');
                    
                    // Reajustar intervalo si hay muchos cambios seguidos
                    if (consecutiveUpdatesRef.current > 0) {
                        adjustPollingInterval();
                    }
                } else {
                    // Sin cambios - resetear contador de actualizaciones consecutivas
                    consecutiveUpdatesRef.current = 0;
                    console.log('✅ No hay cambios');
                    setPollingStatus('active');
                    errorCountRef.current = 0; // Resetear contador de errores
                }
            } else {
                console.log('❌ Error en respuesta del servidor');
                handlePollingError();
            }
        } catch (error) {
            console.log('❌ Error de conexión:', error.message);
            handlePollingError();
        }
    };

    // Manejo de errores optimizado
    const handlePollingError = () => {
        errorCountRef.current++;
        setPollingStatus('error');
        
        // Si hay muchos errores consecutivos, aumentar el intervalo
        if (errorCountRef.current > 3) {
            console.log('⚠️ Muchos errores, aumentando intervalo de polling...');
            restartPollingWithNewInterval(15000); // 15 segundos
        } else if (errorCountRef.current > 1) {
            restartPollingWithNewInterval(10000); // 10 segundos
        }
    };

    // Reajustar intervalo de polling
    const adjustPollingInterval = () => {
        if (consecutiveUpdatesRef.current > 5) {
            console.log('🚀 Alta actividad, manteniendo polling rápido');
        } else if (consecutiveUpdatesRef.current > 2) {
            console.log('⚡ Actividad moderada, ajustando polling');
        }
        
        // Reiniciar polling con nuevo intervalo
        restartPollingWithNewInterval(getPollingInterval());
    };

    // Reiniciar polling con nuevo intervalo
    const restartPollingWithNewInterval = (newInterval) => {
        if (pollingRef.current) {
            clearInterval(pollingRef.current);
        }
        
        console.log(`🔄 Reajustando intervalo de polling a ${newInterval}ms`);
        pollingRef.current = setInterval(() => {
            checkForUpdates();
        }, newInterval);
    };

    // Función para verificar si el usuario puede terminar trabajos
    const canTerminarTrabajos = () => {
        return user && (user.role === 'admin' || user.role === 'tecnico');
    };

    // Función para verificar si el usuario es administrador
    const isAdmin = () => {
        return user && user.role === 'admin';
    };

    // Verificar estado de la API NHTSA
    const checkApiStatus = async () => {
        try {
            const token = localStorage.getItem('token');
            const response = await axios.get('/api/vehicles/status', {
                headers: {
                    'Authorization': `Bearer ${token}`,
                    'Content-Type': 'application/json'
                },
                timeout: 5000
            });
            
            setApiStatus(response.data.status);
        } catch (error) {
            console.error('Error checking API status:', error);
            setApiStatus('offline');
        }
    };

    // Cargar marcas desde la API NHTSA
    const fetchMarcas = async () => {
        try {
            setLoadingMarcas(true);
            const token = localStorage.getItem('token');
            const response = await axios.get('/api/vehicles/makes', {
                headers: {
                    'Authorization': `Bearer ${token}`,
                    'Content-Type': 'application/json'
                },
                timeout: 10000
            });

            if (response.data.success) {
                setMarcasData(response.data.data);
            }
        } catch (error) {
            console.error('Error cargando marcas:', error);
        } finally {
            setLoadingMarcas(false);
        }
    };

    // Cargar años según la marca seleccionada
    const fetchAños = async (marca) => {
        if (!marca) {
            setAñosData([]);
            return;
        }

        try {
            setLoadingAños(true);
            const token = localStorage.getItem('token');
            const response = await axios.get(`/api/vehicles/years/${encodeURIComponent(marca)}`, {
                headers: {
                    'Authorization': `Bearer ${token}`,
                    'Content-Type': 'application/json'
                },
                timeout: 10000
            });

            if (response.data.success) {
                setAñosData(response.data.data);
            }
        } catch (error) {
            console.error('Error cargando años:', error);
            setAñosData([]);
        } finally {
            setLoadingAños(false);
        }
    };

    // Cargar modelos según marca y año seleccionados
    const fetchModelos = async (marca, año) => {
        if (!marca || !año) {
            setModelosData([]);
            return;
        }

        try {
            setLoadingModelos(true);
            const token = localStorage.getItem('token');
            const response = await axios.get(`/api/vehicles/models/${encodeURIComponent(marca)}/${año}`, {
                headers: {
                    'Authorization': `Bearer ${token}`,
                    'Content-Type': 'application/json'
                },
                timeout: 10000
            });

            if (response.data.success) {
                setModelosData(response.data.data);
            }
        } catch (error) {
            console.error('Error cargando modelos:', error);
            setModelosData([]);
        } finally {
            setLoadingModelos(false);
        }
    };

    // Efectos para cargar datos dinámicamente
    useEffect(() => {
        if (formData.marca) {
            fetchAños(formData.marca);
        } else {
            setAñosData([]);
            setModelosData([]);
        }
    }, [formData.marca]);

    useEffect(() => {
        if (formData.marca && formData.año) {
            fetchModelos(formData.marca, formData.año);
        } else {
            setModelosData([]);
        }
    }, [formData.marca, formData.año]);

    const handleAbrirNotas = (trabajo) => {
        setCurrentNotasTrabajo(trabajo);
        setNotasText(trabajo.notas || '');
        setShowNotasPopup(true);
    };

    const handleGuardarNotas = async () => {
        if (!currentNotasTrabajo) return;

        try {
            setGuardandoNotas(true);
            const token = localStorage.getItem('token');
            
            const response = await axios.put(`/api/trabajos/${currentNotasTrabajo.id}/notas`, {
                notas: notasText
            }, {
                headers: {
                    'Authorization': `Bearer ${token}`,
                    'Content-Type': 'application/json'
                },
                timeout: 10000
            });

            if (response.data.success) {
                // Forzar actualización después de guardar notas
                setForceRefresh(prev => prev + 1);
                setShowNotasPopup(false);
            }
        } catch (error) {
            console.error('Error guardando notas:', error);
            alert('Error al guardar las notas');
        } finally {
            setGuardandoNotas(false);
        }
    };

    const handleCancelarNotas = () => {
        setShowNotasPopup(false);
        setCurrentNotasTrabajo(null);
        setNotasText('');
    };

    // Funciones para edición
    const handleAbrirEdicion = (trabajo) => {
        setTrabajoEditando(trabajo);
        setFormDataEditar({
            marca: trabajo.marca,
            modelo: trabajo.modelo,
            año: trabajo.año,
            trabajos: [...trabajo.trabajos],
            color: trabajo.color,
            subtrabajos_seleccionados: { ...(trabajo.subtrabajos_seleccionados || {}) }
        });
        setShowEditarPopup(true);
    };

    const handleEditarChange = (field, value) => {
        setFormDataEditar(prev => ({
            ...prev,
            [field]: value
        }));
    };


    // Función auxiliar para obtener subtrabajos
    const obtenerSubtrabajosPorTipo = (tipoTrabajo) => {
        switch(tipoTrabajo) {
            case "Afinación":
                return [...trabajosData.trabajosAfinacion];
            case "Suspensión":
                return [...trabajosData.trabajosSuspension];
            case "Frenos":
                return [...trabajosData.trabajosFrenos];
            default:
                return [];
        }
    };

    const handleTrabajoEdicionChange = (index, value) => {
        const nuevosTrabajos = [...formDataEditar.trabajos];
        const trabajoAnterior = nuevosTrabajos[index];
        nuevosTrabajos[index] = value;
        
        const nuevosSubtrabajos = { ...formDataEditar.subtrabajos_seleccionados };
        
        if (trabajoAnterior !== value) {
            // Verificar si el trabajo anterior aún existe en otros índices
            const trabajoAnteriorAunExiste = nuevosTrabajos.some((t, i) => i !== index && t === trabajoAnterior);
            
            // Eliminar el trabajo anterior si ya no existe en ningún índice
            if (!trabajoAnteriorAunExiste && trabajoAnterior in nuevosSubtrabajos) {
                delete nuevosSubtrabajos[trabajoAnterior];
            }
            
            // Configurar comportamiento automático para trabajos específicos
            if (["Afinación", "Suspensión", "Frenos"].includes(value)) {
                if (value === "Afinación") {
                    // AFINACIÓN y FRENOS: Marcar TODOS los subtrabajos automáticamente
                    nuevosSubtrabajos[value] = obtenerSubtrabajosPorTipo(value);
                }
                if(value === "Frenos"){
                    nuevosSubtrabajos[value] = obtenerSubtrabajosPorTipo(value);
                } else {
                    // SUSPENSIÓN NO marcar ningún subtrabajo
                    nuevosSubtrabajos[value] = [];
                }
            } else if (value.trim() !== "" && !(value in nuevosSubtrabajos)) {
                nuevosSubtrabajos[value] = [];
            }
        }
        
        setFormDataEditar(prev => ({
            ...prev,
            trabajos: nuevosTrabajos,
            subtrabajos_seleccionados: nuevosSubtrabajos
        }));
    };

    const handleAgregarTrabajoEdicion = () => {
        setFormDataEditar(prev => ({
            ...prev,
            trabajos: [...prev.trabajos, ''] 
        }));
    };

    const handleEliminarTrabajoEdicion = (index) => {
        const trabajoAEliminar = formDataEditar.trabajos[index];
        const nuevosTrabajos = formDataEditar.trabajos.filter((_, i) => i !== index);
        const nuevosSubtrabajos = { ...formDataEditar.subtrabajos_seleccionados };
        
        // Verificar si el trabajo eliminado aún existe en otros índices
        const existeTrabajo = nuevosTrabajos.includes(trabajoAEliminar);
        if (!existeTrabajo && trabajoAEliminar in nuevosSubtrabajos) {
            delete nuevosSubtrabajos[trabajoAEliminar];
        }
        
        setFormDataEditar(prev => ({
            ...prev,
            trabajos: nuevosTrabajos,
            subtrabajos_seleccionados: nuevosSubtrabajos
        }));
    };

    const handleToggleSubtrabajoEdicion = (trabajoType, subtrabajo, checked) => {
        setFormDataEditar(prev => {
            const subtrabajosActuales = prev.subtrabajos_seleccionados[trabajoType] || [];
            let nuevosSubtrabajos;
            
            if (checked) {
                nuevosSubtrabajos = [...subtrabajosActuales, subtrabajo];
            } else {
                nuevosSubtrabajos = subtrabajosActuales.filter(st => st !== subtrabajo);
            }
            
            return {
                ...prev,
                subtrabajos_seleccionados: {
                    ...prev.subtrabajos_seleccionados,
                    [trabajoType]: nuevosSubtrabajos
                }
            };
        });
    };

    // Función de guardar edición
    const handleGuardarEdicion = async () => {
        if (!trabajoEditando) return;

        try {
            setGuardandoEdicion(true);
            const token = localStorage.getItem('token');

            const datosActualizados = {
                marca: formDataEditar.marca,
                modelo: formDataEditar.modelo,
                año: formDataEditar.año,
                trabajos: formDataEditar.trabajos.filter(t => t.trim() !== ''),
                color: formDataEditar.color,
                subtrabajos_seleccionados: formDataEditar.subtrabajos_seleccionados
            };

            console.log('💾 Guardando cambios...');

            const response = await axios.put(`/api/trabajos/${trabajoEditando.id}`, datosActualizados, {
                headers: {
                    'Authorization': `Bearer ${token}`,
                    'Content-Type': 'application/json'
                },
                timeout: 10000
            });

            if (response.data.success) {
                console.log('✅ Cambios guardados');
                
                // Forzar actualización inmediata después de editar
                setForceRefresh(prev => prev + 1);
                
                setShowEditarPopup(false);
                setTrabajoEditando(null);
                
            } else {
                throw new Error(response.data.error || 'Error desconocido del servidor');
            }
        } catch (error) {
            console.error('❌ Error guardando edición:', error);
            alert('Error al guardar los cambios: ' + (error.response?.data?.error || error.message));
        } finally {
            setGuardandoEdicion(false);
        }
    };

    const handleCancelarEdicion = () => {
        setShowEditarPopup(false);
        setTrabajoEditando(null);
        setFormDataEditar({
            marca: '',
            modelo: '',
            año: '',
            trabajos: [],
            subtrabajos_seleccionados: {}
        });
    };

    // Cargar trabajos desde la API - OPTIMIZADA
    const fetchTrabajos = async () => {
        try {
            console.log('🔄 Cargando trabajos...');
            setLoading(true);
            const token = localStorage.getItem('token');
            
            const response = await axios.get(`/api/trabajos?t=${Date.now()}`, {
                headers: {
                    'Authorization': `Bearer ${token}`,
                    'Content-Type': 'application/json'
                },
                timeout: 10000
            });

            if (response.data.success) {
                const trabajosFromAPI = response.data.data;
                console.log('📥 Trabajos recibidos:', trabajosFromAPI.length);
                
                // Obtener el state_hash actualizado
                try {
                    const hashResponse = await axios.get(`/api/trabajos/last-update?t=${Date.now()}`, {
                        headers: {
                            'Authorization': `Bearer ${token}`,
                            'Content-Type': 'application/json'
                        },
                        timeout: 5000
                    });
                    
                    if (hashResponse.data.success) {
                        lastHashRef.current = hashResponse.data.state_hash;
                        console.log('🔐 State hash actualizado');
                    }
                } catch (hashError) {
                    console.log('⚠️ Error obteniendo state hash, continuando...');
                }
                
                const newSections = Array(9).fill(null);
                
                trabajosFromAPI.forEach((trabajo, index) => {
                    if (index < 9) {
                        newSections[index] = {
                            id: trabajo.id,
                            marca: trabajo.marca,
                            modelo: trabajo.modelo,
                            año: trabajo.año,
                            trabajos: [...trabajo.trabajos],
                            color: trabajo.color,
                            fechaIngreso: trabajo.fecha_ingreso,
                            subtrabajosEstado: { ...trabajo.subtrabajos_estado },
                            notas: trabajo.notas || '',
                            subtrabajos_seleccionados: { ...trabajo.subtrabajos_seleccionados }
                        };
                    }
                });
                
                console.log('🆕 Sections actualizadas:', newSections.filter(s => s !== null).length, 'trabajos');
                setSections(newSections);
            }
        } catch (error) {
            console.error('❌ Error cargando trabajos:', error);
        } finally {
            setLoading(false);
        }
    };

    const getAddButtonPosition = () => {
        const hasTrabajos = sections.some(section => section !== null);
        if (!hasTrabajos) {
            return 0;
        }

        for (let i = 0; i < sections.length; i++) {
            if (sections[i] === null) {
                return i;
            }
        }
        return -1;
    };

    const handleAddTrabajo = (sectionIndex) => {
        setCurrentSection(sectionIndex);
        setShowPopup(true);
        setFormData({
            marca: '',
            año: '',
            modelo: '',
            trabajos: ['']
        });
        setTrabajosActivosForm({
            trabajosActivosAfinacion: [],
            trabajosActivosSuspension: [],
            trabajosActivosFrenos: []
        });
    };

    const handleAddTrabajoField = () => {
        setFormData(prev => ({
            ...prev,
            trabajos: [...prev.trabajos, '']
        }));
    };

    const handleInputChange = (field, value, index = null) => {
        if (index !== null) {
            const newTrabajos = [...formData.trabajos];
            newTrabajos[index] = value;
            setFormData(prev => ({ ...prev, trabajos: newTrabajos }));

            if (value === "Afinación") {
                setTrabajosActivosForm(prev => ({
                    ...prev,
                    trabajosActivosAfinacion: [...trabajosData.trabajosAfinacion]
                }));
            } else if (value === "Frenos") {
                setTrabajosActivosForm(prev => ({
                    ...prev,
                    trabajosActivosFrenos: [...trabajosData.trabajosFrenos]
                }));
            } else if (value === "Suspensión") {
                setTrabajosActivosForm(prev => ({
                    ...prev,
                    trabajosActivosSuspension: []
                }));
            }
        } else {
            setFormData(prev => ({ 
                ...prev, 
                [field]: value,
                ...(field === 'marca' && { año: '', modelo: '' }),
                ...(field === 'año' && { modelo: '' })
            }));
        }
    };

    const handleSubtrabajoChangeForm = (trabajoType, subtrabajo, checked) => {
        setTrabajosActivosForm(prev => {
            const key = `trabajosActivos${trabajoType}`;
            if (checked) {
                return {
                    ...prev,
                    [key]: [...prev[key], subtrabajo]
                };
            } else {
                return {
                    ...prev,
                    [key]: prev[key].filter(item => item !== subtrabajo)
                };
            }
        });
    };
    
    // FUNCIÓN ACTUALIZADA: Incluye información del usuario
    const handleSubtrabajoEstadoChange = async (trabajoId, subtrabajo, isGreen) => {
        try {
            // ACTUALIZAR ESTADO LOCAL INMEDIATAMENTE
            setSections(prevSections => {
                return prevSections.map(section => {
                    if (section && section.id === trabajoId) {
                        return {
                            ...section,
                            subtrabajosEstado: {
                                ...section.subtrabajosEstado,
                                [subtrabajo]: isGreen
                            }
                        };
                    }
                    return section;
                });
            });

            const token = localStorage.getItem('token');
            await axios.put(`/api/trabajos/${trabajoId}/subtrabajo`, {
                subtrabajo: subtrabajo,
                estado: isGreen
            }, {
                headers: {
                    'Authorization': `Bearer ${token}`,
                    'Content-Type': 'application/json'
                },
                timeout: 8000
            });

            console.log(`✅ Estado cambiado a ${isGreen ? 'verde' : 'rojo'} por ${user.name}, el polling se encargará de la sincronización`);

        } catch (error) {
            console.error('Error actualizando subtrabajo:', error);
            
            // REVERTIR EL CAMBIO EN CASO DE ERROR
            setSections(prevSections => {
                return prevSections.map(section => {
                    if (section && section.id === trabajoId) {
                        return {
                            ...section,
                            subtrabajosEstado: {
                                ...section.subtrabajosEstado,
                                [subtrabajo]: !isGreen // Revertir al estado anterior
                            }
                        };
                    }
                    return section;
                });
            });
            
            alert('Error al actualizar el estado del trabajo');
        }
    };

    const getBottonEstado = (trabajoId, subtrabajo) => {
        const section = sections.find(s => s && s.id === trabajoId);
        return section ? section.subtrabajosEstado[subtrabajo] || false : false;
    };

    const handleSubmit = async (e) => {
        e.preventDefault();
        
        if (currentSection !== null && formData.marca && formData.modelo && formData.año && formData.trabajos.some(t => t)) {
            try {
                const token = localStorage.getItem('token');
                
                const subtrabajosSeleccionadosData = {};
                
                formData.trabajos.forEach(trabajo => {
                    if (trabajo === "Afinación") {
                        subtrabajosSeleccionadosData["Afinación"] = trabajosActivosForm.trabajosActivosAfinacion;
                    } else if (trabajo === "Suspensión") {
                        subtrabajosSeleccionadosData["Suspensión"] = trabajosActivosForm.trabajosActivosSuspension;
                    } else if (trabajo === "Frenos") {
                        subtrabajosSeleccionadosData["Frenos"] = trabajosActivosForm.trabajosActivosFrenos;
                    }
                });

                const response = await axios.post('/api/trabajos', {
                    marca: formData.marca,
                    modelo: formData.modelo,
                    año: formData.año,
                    trabajos: formData.trabajos.filter(t => t),
                    fecha_ingreso: new Date().toLocaleDateString('es-ES'),
                    subtrabajos_seleccionados: subtrabajosSeleccionadosData
                }, {
                    headers: {
                        'Authorization': `Bearer ${token}`,
                        'Content-Type': 'application/json'
                    },
                    timeout: 10000
                });

                if (response.data.success) {
                    // Forzar actualización después de crear
                    setForceRefresh(prev => prev + 1);
                    setShowPopup(false);
                }

            } catch (error) {
                console.error('Error creando trabajo:', error);
                alert('Error al crear el trabajo');
            }
        }
    };

    const handleTerminarTrabajo = async (trabajoId) => {
        const section = sections.find(s => s && s.id === trabajoId);
        if (!section) return;

        const allSubtrabajos = [];
        
        section.trabajos.forEach(trabajo => {
            if (trabajo === "Afinación") {
                const subtrabajos = section.subtrabajos_seleccionados?.["Afinación"] || trabajosData.trabajosAfinacion;
                allSubtrabajos.push(...subtrabajos);
            } else if (trabajo === "Suspensión") {
                const subtrabajos = section.subtrabajos_seleccionados?.["Suspensión"] || trabajosData.trabajosSuspension;
                allSubtrabajos.push(...subtrabajos);
            } else if (trabajo === "Frenos") {
                const subtrabajos = section.subtrabajos_seleccionados?.["Frenos"] || trabajosData.trabajosFrenos;
                allSubtrabajos.push(...subtrabajos);
            } else {
                allSubtrabajos.push(trabajo);
            }
        });

        const allGreen = allSubtrabajos.every(subtrabajo => 
            getBottonEstado(trabajoId, subtrabajo)
        );

        if (allGreen) {
            try {
                const token = localStorage.getItem('token');
                const response = await axios.delete(`/api/trabajos/${trabajoId}`, {
                    headers: {
                        'Authorization': `Bearer ${token}`,
                        'Content-Type': 'application/json'
                    },
                    timeout: 10000
                });

                if (response.data.success) {
                    // Forzar actualización después de eliminar
                    setForceRefresh(prev => prev + 1);
                } else {
                    console.error('Error del servidor:', response.data);
                    alert('Error del servidor: ' + (response.data.error || 'Error desconocido'));
                }

            } catch (error) {
                console.error('Error terminando trabajo:', error);
                alert('Error al terminar el trabajo: ' + (error.response?.data?.error || error.message));
            }
        } else {
            alert('Todos los botones de los trabajos deben estar en verde para terminar el trabajo.');
        }
    };

    const renderSubtrabajos = (trabajoId, trabajo, index) => {
        const section = sections.find(s => s && s.id === trabajoId);
        
        switch(trabajo) {
            case "Afinación":
                const subtrabajosAfinacion = section?.subtrabajos_seleccionados?.["Afinación"] || trabajosData.trabajosAfinacion;
                return (
                    <div key={`afinacion-${index}`} className="subtrabajos-container">
                        <div className="custom-content-trabajo">Afinación</div>
                        {subtrabajosAfinacion.map((subtrabajo, subIndex) => (
                            <SubtrabajoItem 
                                key={subIndex} 
                                trabajoId={trabajoId}
                                subtrabajo={subtrabajo}
                                isGreen={getBottonEstado(trabajoId, subtrabajo)}
                                onToggle={(isGreen) => handleSubtrabajoEstadoChange(trabajoId, subtrabajo, isGreen)}
                            />
                        ))}
                    </div>
                );
            case "Suspensión":
                const subtrabajosSuspension = section?.subtrabajos_seleccionados?.["Suspensión"] || trabajosData.trabajosSuspension;
                return (
                    <div key={`suspension-${index}`} className="subtrabajos-container">
                        <div className="custom-content-trabajo">Suspensión</div>
                        {subtrabajosSuspension.map((subtrabajo, subIndex) => (
                            <SubtrabajoItem 
                                key={subIndex} 
                                trabajoId={trabajoId}
                                subtrabajo={subtrabajo}
                                isGreen={getBottonEstado(trabajoId, subtrabajo)}
                                onToggle={(isGreen) => handleSubtrabajoEstadoChange(trabajoId, subtrabajo, isGreen)}
                            />
                        ))}
                    </div>
                );
            case "Frenos":
                const subtrabajosFrenos = section?.subtrabajos_seleccionados?.["Frenos"] || trabajosData.trabajosFrenos;
                return (
                    <div key={`frenos-${index}`} className="subtrabajos-container">
                        <div className="custom-content-trabajo">Frenos</div>
                        {subtrabajosFrenos.map((subtrabajo, subIndex) => (
                            <SubtrabajoItem 
                                key={subIndex} 
                                trabajoId={trabajoId}
                                subtrabajo={subtrabajo}
                                isGreen={getBottonEstado(trabajoId, subtrabajo)}
                                onToggle={(isGreen) => handleSubtrabajoEstadoChange(trabajoId, subtrabajo, isGreen)}
                            />
                        ))}
                    </div>
                );
            default:
                return (
                    <SubtrabajoItem 
                        key={`default-${index}`}
                        trabajoId={trabajoId}
                        subtrabajo={trabajo}
                        isGreen={getBottonEstado(trabajoId, trabajo)}
                        onToggle={(isGreen) => handleSubtrabajoEstadoChange(trabajoId, trabajo, isGreen)}
                    />
                );
        }
    };

    const addButtonPosition = getAddButtonPosition();

    const PollingStatusIndicator = () => {
        const getStatusInfo = () => {
            switch(pollingStatus) {
                case 'active':
                    return { text: '🟢 Sincronizado', color: 'connected' };
                case 'updating':
                    return { text: '🔄 Actualizando...', color: 'updating' };
                case 'error':
                    return { text: '⚠️ Error de conexión', color: 'error' };
                default:
                    return { text: '⚪ Inactivo', color: 'inactive' };
            }
        };

        const status = getStatusInfo();

        return (
            <div className={`polling-status ${status.color}`}>
                {status.text}
                {errorCountRef.current > 0 && (
                    <span className="error-count"> ({errorCountRef.current})</span>
                )}
            </div>
        );
    };

    if (loading) {
        return (
            <div className="loading">
                Cargando trabajos...
            </div>
        );
    }

    return (
        <div className="dashboard-panel">  
            <div className="trabajos-grid">
                <div className="row rowcustom">
                    {[0, 1, 2].map(index => (
                        <TrabajoSection 
                            key={index}
                            index={index}
                            data={sections[index]}
                            showAddButton={index === addButtonPosition}
                            onAddTrabajo={handleAddTrabajo}
                            onTerminarTrabajo={handleTerminarTrabajo}
                            onAbrirNotas={handleAbrirNotas}
                            onAbrirEdicion={handleAbrirEdicion}
                            renderSubtrabajos={renderSubtrabajos}
                            canTerminar={canTerminarTrabajos()}
                            isAdmin={isAdmin()}
                        />
                    ))}
                </div>
                <div className="row rowcustom">
                    {[3, 4, 5].map(index => (
                        <TrabajoSection 
                            key={index}
                            index={index}
                            data={sections[index]}
                            showAddButton={index === addButtonPosition}
                            onAddTrabajo={handleAddTrabajo}
                            onTerminarTrabajo={handleTerminarTrabajo}
                            onAbrirNotas={handleAbrirNotas}
                            onAbrirEdicion={handleAbrirEdicion}
                            renderSubtrabajos={renderSubtrabajos}
                            canTerminar={canTerminarTrabajos()}
                            isAdmin={isAdmin()}
                        />
                    ))}
                </div>
                <div className="row rowcustom">
                    {[6, 7, 8].map(index => (
                        <TrabajoSection 
                            key={index}
                            index={index}
                            data={sections[index]}
                            showAddButton={index === addButtonPosition}
                            onAddTrabajo={handleAddTrabajo}
                            onTerminarTrabajo={handleTerminarTrabajo}
                            onAbrirNotas={handleAbrirNotas}
                            onAbrirEdicion={handleAbrirEdicion}
                            renderSubtrabajos={renderSubtrabajos}
                            canTerminar={canTerminarTrabajos()}
                            isAdmin={isAdmin()}
                        />
                    ))}
                </div>
            </div>

            {showPopup && (
                <TrabajoPopup 
                    formData={formData}
                    marcasData={marcasData}
                    añosData={añosData}
                    modelosData={modelosData}
                    loadingMarcas={loadingMarcas}
                    loadingAños={loadingAños}
                    loadingModelos={loadingModelos}
                    apiStatus={apiStatus}
                    trabajosData={trabajosData}
                    trabajosActivosForm={trabajosActivosForm}
                    onInputChange={handleInputChange}
                    onAddTrabajoField={handleAddTrabajoField}
                    onSubtrabajoChange={handleSubtrabajoChangeForm}
                    onSubmit={handleSubmit}
                    onClose={() => setShowPopup(false)}
                />
            )}

            {showNotasPopup && (
                <NotasPopup 
                    notas={notasText}
                    onNotasChange={setNotasText}
                    onGuardar={handleGuardarNotas}
                    onCancelar={handleCancelarNotas}
                    guardando={guardandoNotas}
                    vehiculo={currentNotasTrabajo ? `${currentNotasTrabajo.marca} ${currentNotasTrabajo.modelo} ${currentNotasTrabajo.año}` : ''}
                />
            )}

            {showEditarPopup && (
                <EditarPopup 
                    formData={formDataEditar}
                    trabajosData={trabajosData}
                    onInputChange={handleEditarChange}
                    onTrabajoChange={handleTrabajoEdicionChange}
                    onAgregarTrabajo={handleAgregarTrabajoEdicion}
                    onEliminarTrabajo={handleEliminarTrabajoEdicion}
                    onToggleSubtrabajo={handleToggleSubtrabajoEdicion}
                    onGuardar={handleGuardarEdicion}
                    onCancelar={handleCancelarEdicion}
                    guardando={guardandoEdicion}
                    vehiculo={trabajoEditando ? `${trabajoEditando.marca} ${trabajoEditando.modelo} ${trabajoEditando.año}` : ''}
                />
            )}
        </div>
    );
};

// Componentes auxiliares
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

const TrabajoSection = ({ 
    index, 
    data, 
    showAddButton, 
    onAddTrabajo, 
    onTerminarTrabajo, 
    onAbrirNotas,
    onAbrirEdicion,
    renderSubtrabajos,
    canTerminar,
    isAdmin
}) => {
    const sectionId = `section${index + 1}`;

    if (!data && showAddButton) {
        return (
            <div className="col section empty-section" id={sectionId}>
                <div className="btn-container">
                    <button 
                        className="btn btn-custom"
                        onClick={() => onAddTrabajo(index)}
                    >
                        Nuevo Trabajo
                    </button>
                </div>
            </div>
        );
    }

    if (!data) {
        return (
            <div className="col section empty-section" id={sectionId}>
                {/* Sección vacía sin botón */}
            </div>
        );
    }

    return (
        <div 
            className="col section" 
            id={sectionId}
            style={{ backgroundColor: data.color }}
        >
            <div className="custom-content">
                <div className="sectiontitle">
                    {data.marca} {data.modelo} {data.año}
                </div>
                
                <div className="subtrabajos-list">
                    {data.trabajos.map((trabajo, trabajoIndex) => 
                        trabajo && renderSubtrabajos(data.id, trabajo, trabajoIndex)
                    )}
                </div>
            </div>

            <div className="custom-content-fecha">
                Fecha de ingreso: {data.fechaIngreso}
            </div>

            <div className="delete-button-container">
                <button 
                    className="btn btn-notas"
                    onClick={() => onAbrirNotas(data)}
                    title="Agregar o ver notas"
                >
                    📝
                </button>

                {/* Botón de Editar - Solo para administradores */}
                {isAdmin && (
                    <button 
                        className="btn btn-editar"
                        onClick={() => onAbrirEdicion(data)}
                        title="Editar información del vehículo"
                    >
                        ✏️
                    </button>
                )}
                
                {canTerminar && (
                    <button 
                        className="btn btn-customD"
                        onClick={() => onTerminarTrabajo(data.id)}
                    >
                        Terminar trabajo
                    </button>
                )}
            </div>
        </div>
    );
};

const SubtrabajoItem = ({ trabajoId, subtrabajo, isGreen, onToggle }) => {
    const handleClick = () => {
        const newState = !isGreen;
        onToggle(newState);
    };

    return (
        <div className="custom-content-trabajo2">
            <div className="subtrabajo-text">{subtrabajo}</div>
            <button 
                className={`color-btn-trabajo ${isGreen ? 'green' : 'red'}`}
                onClick={handleClick}
            />
        </div>
    );
};

const TrabajoPopup = ({ 
    formData, 
    marcasData, 
    añosData, 
    modelosData,
    loadingMarcas,
    loadingAños,
    loadingModelos,
    apiStatus,
    trabajosData,
    trabajosActivosForm,
    onInputChange, 
    onAddTrabajoField, 
    onSubtrabajoChange, 
    onSubmit, 
    onClose 
}) => {
    return (
        <div className="popup-overlay">
            <div className="popup">
                <div className="popup-content">
                    <h2>Añadir Vehículo</h2>
                    
                    <form onSubmit={onSubmit}>
                        <div className="form-group">
                            <label htmlFor="marca">Marca:</label>
                            <input
                                type="text"
                                id="marca"
                                value={formData.marca}
                                onChange={(e) => onInputChange('marca', e.target.value)}
                                list="marcas-lista"
                                placeholder="Escribe o selecciona una marca"
                                required
                                disabled={loadingMarcas}
                                className="custom-select"
                            />
                            <datalist id="marcas-lista">
                                <option value="">
                                    {loadingMarcas ? '🔄 Cargando marcas...' : 'Elige una marca...'}
                                </option>
                                {marcasData.map((marca, index) => (
                                    <option key={index} value={marca}>
                                        {marca}
                                    </option>
                                ))}
                            </datalist>
                        </div>

                        <div className="form-group">
                            <label htmlFor="año">Año:</label>
                            <input
                                type="text"
                                id="año"
                                value={formData.año}
                                onChange={(e) => {
                                    // Filtrar solo números
                                    const soloNumeros = e.target.value.replace(/[^0-9]/g, '');
                                    onInputChange('año', soloNumeros);
                                }}
                                list="años-lista"
                                placeholder="Selecciona o escribe un año"
                                required
                                disabled={!formData.marca || loadingAños}
                                className="custom-select"
                                maxLength="4" // Limitar a 4 dígitos para años
                                pattern="[0-9]*"
                                inputMode="numeric" 
                            />
                            <datalist id="años-lista">
                                <option value="">
                                    {loadingAños ? '🔄 Cargando años...' : 
                                    !formData.marca ? 'Primero selecciona una marca' : 'Elige un año...'}
                                </option>
                                {añosData.map((año, index) => (
                                    <option key={index} value={año}>
                                        {año}
                                    </option>
                                ))}
                            </datalist>
                        </div>

                        <div className="form-group">
                            <label htmlFor="modelo">Modelo:</label>
                            <input
                                type="text"
                                id="modelo"
                                value={formData.modelo}
                                onChange={(e) => onInputChange('modelo', e.target.value)}
                                list="modelos-lista"
                                placeholder="Escribe o selecciona un modelo"
                                required
                                disabled={!formData.año || loadingModelos}
                                className="custom-select"
                            />
                            <datalist id="modelos-lista">
                                <option value="">
                                    {loadingModelos ? '🔄 Cargando modelos...' : 
                                     !formData.año ? 'Primero selecciona un año' : 'Elige un modelo...'}
                                </option>
                                {modelosData.map((modelo, index) => (
                                    <option key={index} value={modelo}>
                                        {modelo}
                                    </option>
                                ))}
                            </datalist>
                        </div>

                        <div id="trabajosContainer">
                            {formData.trabajos.map((trabajo, index) => (
                                <div key={index} className="trabajo-group">
                                    <div className="form-group">
                                        <label htmlFor={`trabajo${index}`}>
                                            Trabajo {index + 1}:
                                        </label>
                                        <input
                                            type="text"
                                            id={`trabajo${index}`}
                                            value={trabajo}
                                            onChange={(e) => onInputChange('trabajo', e.target.value, index)}
                                            list="trabajolista"
                                            placeholder="Escribe o selecciona un trabajo"
                                            required
                                        />
                                    </div>
                                    
                                    {trabajo === "Afinación" && (
                                        <div className="subtrabajos-checkbox-group">
                                            <label className="subtrabajos-label">Seleccionar subtrabajos de Afinación:</label>
                                            <CheckboxGroup 
                                                trabajos={trabajosData.trabajosAfinacion}
                                                trabajosActivos={trabajosActivosForm.trabajosActivosAfinacion}
                                                onToggle={(subtrabajo, checked) => 
                                                    onSubtrabajoChange('Afinacion', subtrabajo, checked)
                                                }
                                            />
                                        </div>
                                    )}
                                    
                                    {trabajo === "Suspensión" && (
                                        <div className="subtrabajos-checkbox-group">
                                            <label className="subtrabajos-label">Seleccionar subtrabajos de Suspensión:</label>
                                            <CheckboxGroup 
                                                trabajos={trabajosData.trabajosSuspension}
                                                trabajosActivos={trabajosActivosForm.trabajosActivosSuspension}
                                                onToggle={(subtrabajo, checked) => 
                                                    onSubtrabajoChange('Suspension', subtrabajo, checked)
                                                }
                                            />
                                        </div>
                                    )}
                                    
                                    {trabajo === "Frenos" && (
                                        <div className="subtrabajos-checkbox-group">
                                            <label className="subtrabajos-label">Seleccionar subtrabajos de Frenos:</label>
                                            <CheckboxGroup 
                                                trabajos={trabajosData.trabajosFrenos}
                                                trabajosActivos={trabajosActivosForm.trabajosActivosFrenos}
                                                onToggle={(subtrabajo, checked) => 
                                                    onSubtrabajoChange('Frenos', subtrabajo, checked)
                                                }
                                            />
                                        </div>
                                    )}
                                </div>
                            ))}
                        </div>

                        <datalist id="trabajolista">
                            <option value="Afinación" />
                            <option value="Frenos" />
                            <option value="Suspensión" />
                        </datalist>

                        <button type="button" onClick={onAddTrabajoField} className="btn-secondary">
                            + Añadir otro trabajo
                        </button>

                        <div className="form-actions">
                            <button type="submit" className="btn-success">
                                Agregar Vehículo
                            </button>
                            <button type="button" onClick={onClose} className="btn-cancel">
                                Cancelar
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    );
};

const CheckboxGroup = ({ trabajos, trabajosActivos, onToggle }) => {
    return (
        <div className="ContenedorAceite">
            {trabajos.map((trabajo, index) => (
                <div key={index} className="form-check ContenedorAceite">
                    <label 
                        className="form-check-label"
                        htmlFor={`checkbox-${trabajo.replace(/\s+/g, '-').toLowerCase()}`}
                    >
                        {trabajo}
                    </label>

                    <input
                        className="Checkbox"
                        type="checkbox"
                        id={`checkbox-${trabajo.replace(/\s+/g, '-').toLowerCase()}`}
                        checked={trabajosActivos.includes(trabajo)}
                        onChange={(e) => onToggle(trabajo, e.target.checked)}
                        value={trabajo}
                    />
                </div>
            ))}
        </div>
    );
};

// Componente EditarPopup
const EditarPopup = ({ 
    formData, 
    trabajosData,
    onInputChange, 
    onTrabajoChange, 
    onAgregarTrabajo, 
    onEliminarTrabajo,
    onToggleSubtrabajo,
    onGuardar, 
    onCancelar, 
    guardando,
    vehiculo 
}) => {
    
    const renderSubtrabajosEdicion = (trabajo, index) => {
        if (!["Afinación", "Suspensión", "Frenos"].includes(trabajo)) {
            return null;
        }

        let todosLosSubtrabajos = [];
        let subtrabajosSeleccionados = formData.subtrabajos_seleccionados[trabajo] || [];

        switch(trabajo) {
            case "Afinación":
                todosLosSubtrabajos = trabajosData.trabajosAfinacion;
                break;
            case "Suspensión":
                todosLosSubtrabajos = trabajosData.trabajosSuspension;
                break;
            case "Frenos":
                todosLosSubtrabajos = trabajosData.trabajosFrenos;
                break;
            default:
                return null;
        }

        return (
            <div key={`subtrabajos-${index}`} className="subtrabajos-checkbox-group">
                <label className="subtrabajos-label">
                    Seleccionar subtrabajos de {trabajo}:
                </label>
                
                <div className="ContenedorAceite">
                    {todosLosSubtrabajos.map((subtrabajo, subIndex) => (
                        <div key={subIndex} className="form-check ContenedorAceite">
                            <label 
                                className="form-check-label"
                                htmlFor={`checkbox-edicion-${trabajo}-${subtrabajo.replace(/\s+/g, '-').toLowerCase()}`}
                            >
                                {subtrabajo}
                            </label>

                            <input
                                className="Checkbox"
                                type="checkbox"
                                id={`checkbox-edicion-${trabajo}-${subtrabajo.replace(/\s+/g, '-').toLowerCase()}`}
                                checked={subtrabajosSeleccionados.includes(subtrabajo)}
                                onChange={(e) => onToggleSubtrabajo(trabajo, subtrabajo, e.target.checked)}
                                value={subtrabajo}
                            />
                        </div>
                    ))}
                </div>
            </div>
        );
    };

    return (
        <div className="popup-overlay">
            <div className="popup popup-grande">
                <div className="popup-content">
                    <h2>Editar Vehículo</h2>
                    <p><strong>Vehículo:</strong> {vehiculo}</p>
                    
                    <form onSubmit={(e) => { e.preventDefault(); onGuardar(); }}>
                        <div className="form-row">
                            <div className="form-group">
                                <label htmlFor="editar-marca">Marca:</label>
                                <input
                                    type="text"
                                    id="editar-marca"
                                    value={formData.marca}
                                    onChange={(e) => onInputChange('marca', e.target.value)}
                                    required
                                    className="custom-select"
                                />
                            </div>

                            <div className="form-group">
                                <label htmlFor="editar-modelo">Modelo:</label>
                                <input
                                    type="text"
                                    id="editar-modelo"
                                    value={formData.modelo}
                                    onChange={(e) => onInputChange('modelo', e.target.value)}
                                    required
                                    className="custom-select"
                                />
                            </div>

                            <div className="form-group">
                                <label htmlFor="editar-año">Año:</label>
                                <input
                                    type="text"
                                    id="editar-año"
                                    value={formData.año}
                                    onChange={(e) => {
                                        // Filtrar solo números
                                        const soloNumeros = e.target.value.replace(/[^0-9]/g, '');
                                        onInputChange('año', soloNumeros);
                                    }}
                                    required
                                    className="custom-select"
                                    maxLength="4"
                                    pattern="[0-9]*"
                                    inputMode="numeric"
                                    placeholder="Año"
                                />
                            </div>
                        </div>

                        <div className="trabajos-edicion-container">
                            <label className="trabajos-label">Trabajos:</label>
                            {formData.trabajos.map((trabajo, index) => (
                                <div key={index} className="trabajo-edicion-group">
                                    <div className="trabajo-edicion-item">
                                        <input
                                            type="text"
                                            value={trabajo}
                                            onChange={(e) => onTrabajoChange(index, e.target.value)}
                                            list="trabajolista-edicion"
                                            placeholder={`Trabajo ${index + 1}`}
                                            className="trabajo-input"
                                        />
                                        <button
                                            type="button"
                                            onClick={() => onEliminarTrabajo(index)}
                                            className="btn-eliminar-trabajo"
                                            disabled={formData.trabajos.length <= 1}
                                            title="Eliminar este trabajo"
                                        >
                                            🗑️
                                        </button>
                                    </div>
                                    
                                    {/* Renderizar subtrabajos para este trabajo */}
                                    {renderSubtrabajosEdicion(trabajo, index)}
                                </div>
                            ))}
                        </div>

                        <datalist id="trabajolista-edicion">
                            <option value="Afinación" />
                            <option value="Frenos" />
                            <option value="Suspensión" />
                        </datalist>

                        <button 
                            type="button" 
                            onClick={onAgregarTrabajo} 
                            className="btn-secondary"
                        >
                            + Añadir otro trabajo
                        </button>

                        <div className="form-actions">
                            <button 
                                type="submit" 
                                className="btn-success"
                                disabled={guardando}
                            >
                                {guardando ? 'Guardando...' : 'Guardar Cambios'}
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
                    </form>
                </div>
            </div>
        </div>
    );
};

export default TrabajosPanel;