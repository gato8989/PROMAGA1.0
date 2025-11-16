import React, { useState, useEffect } from 'react';
import axios from 'axios';

const AdminPanel = () => {
    const [users, setUsers] = useState([]);
    const [roles, setRoles] = useState([]);
    const [showForm, setShowForm] = useState(false);
    const [loading, setLoading] = useState(true);
    const [formLoading, setFormLoading] = useState(false);
    const [error, setError] = useState('');
    const [editingUser, setEditingUser] = useState(null);
    const [deleteConfirm, setDeleteConfirm] = useState(null);
    
    const [formData, setFormData] = useState({
        name: '',
        email: '',
        password: '',
        role_id: ''
    });

    useEffect(() => {
        console.log('🔄 AdminPanel montado - iniciando carga de datos');
        setTimeout(() => {
            fetchUsers();
            fetchRoles();
        }, 100);
    }, []);

    const fetchUsers = async () => {
        try {
            console.log('📡 Iniciando fetchUsers...');
            setError('');
            setLoading(true);
            
            const token = localStorage.getItem('token');
            if (!token) {
                throw new Error('No hay token disponible');
            }
            
            console.log('🔑 Token disponible, haciendo petición...');
            const response = await axios.get('/api/users', {
                headers: {
                    'Authorization': `Bearer ${token}`,
                    'Content-Type': 'application/json'
                }
            });
            console.log('✅ Usuarios cargados exitosamente:', response.data);
            
            setUsers(response.data);
            
        } catch (err) {
            console.error('❌ Error en fetchUsers:', err);
            console.error('📊 Detalles del error:', {
                status: err.response?.status,
                data: err.response?.data,
                message: err.message
            });
            
            if (err.response?.status === 401) {
                setError('Sesión expirada. Por favor, vuelve a iniciar sesión.');
            } else {
                setError('Error al cargar usuarios: ' + 
                    (err.response?.data?.error || err.message));
            }
        } finally {
            setLoading(false);
        }
    };

    const fetchRoles = async () => {
        try {
            console.log('📡 Iniciando fetchRoles...');
            const token = localStorage.getItem('token');
            const response = await axios.get('/api/roles', {
                headers: {
                    'Authorization': `Bearer ${token}`,
                    'Content-Type': 'application/json'
                }
            });
            console.log('✅ Roles cargados:', response.data);
            setRoles(response.data);
            
            if (response.data.length > 0 && !formData.role_id) {
                setFormData(prev => ({
                    ...prev,
                    role_id: response.data[0].id
                }));
            }
        } catch (err) {
            console.error('❌ Error en fetchRoles:', err);
        }
    };

    const handleSubmit = async (e) => {
        e.preventDefault();
        setFormLoading(true);
        setError('');
        
        try {
            console.log('📤 Enviando formulario:', formData);
            
            const token = localStorage.getItem('token');
            const user = JSON.parse(localStorage.getItem('user'));
            
            console.log('🔐 Usuario autenticado:', user);
            console.log('🔑 Token disponible:', !!token);
            
            const config = {
                headers: {
                    'Authorization': `Bearer ${token}`,
                    'Content-Type': 'application/json'
                }
            };
            
            let response;
            if (editingUser) {
                console.log('✏️ Actualizando usuario ID:', editingUser.id);
                response = await axios.put(`/api/users/${editingUser.id}`, formData, config);
                console.log('✅ Usuario actualizado:', response.data);
            } else {
                console.log('🆕 Creando nuevo usuario');
                response = await axios.post('/api/users', formData, config);
                console.log('✅ Usuario creado:', response.data);
            }
            
            setShowForm(false);
            setEditingUser(null);
            resetForm();
            fetchUsers();
            
            alert(editingUser ? 'Usuario actualizado exitosamente' : 'Usuario creado exitosamente');
            
        } catch (err) {
            console.error('❌ Error en formulario:', err);
            console.error('📊 Detalles completos del error:', {
                status: err.response?.status,
                data: err.response?.data,
                message: err.message,
                config: err.config
            });
            
            const errorMessage = err.response?.data?.error || 
                               err.response?.data?.message || 
                               err.message ||
                               'Error desconocido';
            
            // Mostrar errores de validación detallados
            if (err.response?.data?.errors) {
                const validationErrors = Object.values(err.response.data.errors).flat().join(', ');
                setError('Error de validación: ' + validationErrors);
            } else {
                setError('Error al ' + (editingUser ? 'actualizar' : 'crear') + ' usuario: ' + errorMessage);
            }
        } finally {
            setFormLoading(false);
        }
    };

    const handleEdit = (user) => {
        console.log('✏️ Editando usuario:', user);
        setEditingUser(user);
        setFormData({
            name: user.name,
            email: user.email,
            password: '', // No mostrar contraseña actual por seguridad
            role_id: user.role_id || user.role?.id
        });
        setShowForm(true);
        setError('');
    };

    const handleDelete = async (userId) => {
        if (!window.confirm('¿Estás seguro de que quieres eliminar este usuario? Esta acción no se puede deshacer.')) {
            return;
        }

        try {
            console.log('🗑️ Eliminando usuario:', userId);
            setError('');
            
            const token = localStorage.getItem('token');
            await axios.delete(`/api/users/${userId}`, {
                headers: {
                    'Authorization': `Bearer ${token}`,
                    'Content-Type': 'application/json'
                }
            });
            console.log('✅ Usuario eliminado exitosamente');
            
            fetchUsers();
            setDeleteConfirm(null);
            
            alert('Usuario eliminado exitosamente');
            
        } catch (err) {
            console.error('❌ Error eliminando usuario:', err);
            setError('Error al eliminar usuario: ' + 
                (err.response?.data?.error || err.response?.data?.message || err.message));
        }
    };

    const handleChange = (e) => {
        setFormData({
            ...formData,
            [e.target.name]: e.target.value
        });
    };

    const resetForm = () => {
        setFormData({
            name: '',
            email: '',
            password: '',
            role_id: roles.length > 0 ? roles[0].id : ''
        });
        setEditingUser(null);
        setError('');
    };

    const cancelForm = () => {
        setShowForm(false);
        setEditingUser(null);
        resetForm();
    };

    console.log('🎨 Renderizando AdminPanel:', {
        usersCount: users.length,
        rolesCount: roles.length,
        loading,
        showForm,
        editingUser,
        formData
    });

    return (
        <div className="admin-panel">
            <div className="panel-header">
                <h2>Panel de Administración</h2>
                <div className="panel-actions" style={{
                    display: 'flex',
                    gap: '10px',
                    flexWrap: 'wrap'
                }}>
                    <button 
                        onClick={fetchUsers}
                        className="btn-secondary"                       
                        disabled={loading}
                        style={{
                            padding: '12px 20px',
                            border: 'none',
                            borderRadius: '8px',
                            backgroundColor: '#718096',
                            color: 'white',
                            fontSize: '14px',
                            fontWeight: '600',
                            cursor: loading ? 'not-allowed' : 'pointer',
                            opacity: loading ? 0.6 : 1,
                            whiteSpace: 'nowrap',
                            flexShrink: 0
                        }}
                    >
                        {loading ? 'Cargando...' : 'Actualizar'}
                    </button>
                    <button 
                        onClick={() => {
                            setShowForm(!showForm);
                            if (!showForm) resetForm();
                        }}
                        className="btn-primary"
                        disabled={loading}
                        style={{
                            padding: '12px 20px',
                            border: 'none',
                            borderRadius: '8px',
                            backgroundColor: '#667eea',
                            color: 'white',
                            fontSize: '14px',
                            fontWeight: '600',
                            cursor: loading ? 'not-allowed' : 'pointer',
                            opacity: loading ? 0.6 : 1,
                            whiteSpace: 'nowrap',
                            flexShrink: 0
                        }}
                    >
                        {showForm ? 'Cancelar' : 'Nuevo Usuario'}
                    </button>
                </div>
            </div>

            {error && (
                <div className="error-message" style={{
                    background: '#fed7d7',
                    border: '1px solid #feb2b2',
                    color: '#c53030',
                    padding: '12px 16px',
                    borderRadius: '8px',
                    marginBottom: '20px',
                    display: 'flex',
                    justifyContent: 'space-between',
                    alignItems: 'center'
                }}>
                    <div>
                        <strong>Error:</strong> {error}
                    </div>
                    <button 
                        onClick={() => setError('')} 
                        style={{
                            background: 'transparent',
                            border: 'none',
                            color: '#c53030',
                            cursor: 'pointer',
                            fontSize: '16px',
                            fontWeight: 'bold'
                        }}
                    >
                        ×
                    </button>
                </div>
            )}

            {loading ? (
                <div className="loading" style={{textAlign: 'center', padding: '40px'}}>
                    <p>Cargando usuarios...</p>
                    <button 
                        onClick={fetchUsers} 
                        className="btn-primary" 
                        style={{marginTop: '10px'}}
                    >
                        Reintentar
                    </button>
                </div>
            ) : (
                <>
                    {showForm && (
                        <form onSubmit={handleSubmit} className="user-form" style={{
                            background: '#f7fafc',
                            padding: '20px',
                            borderRadius: '8px',
                            marginBottom: '20px',
                            border: '1px solid #e2e8f0'
                        }}>
                            <h3 style={{marginTop: 0, marginBottom: '20px'}}>
                                {editingUser ? 'Editar Usuario' : 'Crear Nuevo Usuario'}
                            </h3>
                            
                            <div className="form-group" style={{marginBottom: '15px'}}>
                                <label htmlFor="name" style={{
                                    display: 'block',
                                    marginBottom: '5px',
                                    fontWeight: '600'
                                }}>Nombre completo:</label>
                                <input
                                    type="text"
                                    id="name"
                                    name="name"
                                    placeholder="Nombre completo"
                                    value={formData.name}
                                    onChange={handleChange}
                                    required
                                    disabled={formLoading}
                                    style={{
                                        width: '100%',
                                        padding: '10px',
                                        border: '1px solid #cbd5e0',
                                        borderRadius: '4px',
                                        fontSize: '14px'
                                    }}
                                />
                            </div>

                            <div className="form-group" style={{marginBottom: '15px'}}>
                                <label htmlFor="email" style={{
                                    display: 'block',
                                    marginBottom: '5px',
                                    fontWeight: '600'
                                }}>Correo electrónico:</label>
                                <input
                                    type="email"
                                    id="email"
                                    name="email"
                                    placeholder="Correo electrónico"
                                    value={formData.email}
                                    onChange={handleChange}
                                    required
                                    disabled={formLoading}
                                    style={{
                                        width: '100%',
                                        padding: '10px',
                                        border: '1px solid #cbd5e0',
                                        borderRadius: '4px',
                                        fontSize: '14px'
                                    }}
                                />
                            </div>

                            <div className="form-group" style={{marginBottom: '15px'}}>
                                <label htmlFor="password" style={{
                                    display: 'block',
                                    marginBottom: '5px',
                                    fontWeight: '600'
                                }}>
                                    Contraseña:
                                    {editingUser && (
                                        <span style={{fontSize: '12px', color: '#666', marginLeft: '8px'}}>
                                            (Dejar vacío para mantener la actual)
                                        </span>
                                    )}
                                </label>
                                <input
                                    type="password"
                                    id="password"
                                    name="password"
                                    placeholder={editingUser ? "Nueva contraseña (opcional)" : "Contraseña (mínimo 6 caracteres)"}
                                    value={formData.password}
                                    onChange={handleChange}
                                    minLength={editingUser ? "0" : "6"}
                                    disabled={formLoading}
                                    style={{
                                        width: '100%',
                                        padding: '10px',
                                        border: '1px solid #cbd5e0',
                                        borderRadius: '4px',
                                        fontSize: '14px'
                                    }}
                                />
                            </div>

                            <div className="form-group" style={{marginBottom: '20px'}}>
                                <label htmlFor="role_id" style={{
                                    display: 'block',
                                    marginBottom: '5px',
                                    fontWeight: '600'
                                }}>Rol:</label>
                                <select 
                                    id="role_id"
                                    name="role_id" 
                                    value={formData.role_id} 
                                    onChange={handleChange}
                                    disabled={formLoading || roles.length === 0}
                                    required
                                    style={{
                                        width: '100%',
                                        padding: '10px',
                                        border: '1px solid #cbd5e0',
                                        borderRadius: '4px',
                                        fontSize: '14px',
                                        background: 'white'
                                    }}
                                >
                                    {roles.length === 0 ? (
                                        <option value="">Cargando roles...</option>
                                    ) : (
                                        <>
                                            <option value="">Selecciona un rol</option>
                                            {roles.map(role => (
                                                <option key={role.id} value={role.id}>
                                                    {role.description} ({role.name})
                                                </option>
                                            ))}
                                        </>
                                    )}
                                </select>
                            </div>

                            <div className="form-actions" style={{
                                display: 'flex',
                                gap: '10px',
                                flexWrap: 'wrap'
                            }}>
                                <button 
                                    type="submit" 
                                    className="btn-success"
                                    disabled={formLoading || !formData.role_id || !formData.name || !formData.email || (!editingUser && !formData.password)}
                                    style={{
                                        padding: '12px 20px',
                                        border: 'none',
                                        borderRadius: '8px',
                                        backgroundColor: '#48bb78',
                                        color: 'white',
                                        fontSize: '14px',
                                        fontWeight: '600',
                                        cursor: formLoading ? 'not-allowed' : 'pointer',
                                        opacity: (formLoading || !formData.role_id || !formData.name || !formData.email || (!editingUser && !formData.password)) ? 0.6 : 1
                                    }}
                                >
                                    {formLoading 
                                        ? (editingUser ? 'Actualizando...' : 'Creando...') 
                                        : (editingUser ? 'Actualizar Usuario' : 'Crear Usuario')
                                    }
                                </button>
                                <button 
                                    type="button" 
                                    onClick={cancelForm}
                                    className="btn-secondary"
                                    disabled={formLoading}
                                    style={{
                                        padding: '12px 20px',
                                        border: 'none',
                                        borderRadius: '8px',
                                        backgroundColor: '#718096',
                                        color: 'white',
                                        fontSize: '14px',
                                        fontWeight: '600',
                                        cursor: formLoading ? 'not-allowed' : 'pointer'
                                    }}
                                >
                                    Cancelar
                                </button>
                            </div>
                        </form>
                    )}

                    <div className="users-table">
                        <div style={{
                            display: 'flex', 
                            justifyContent: 'space-between', 
                            alignItems: 'center', 
                            marginBottom: '20px'
                        }}>
                            <h3 style={{margin: 0}}>Usuarios del Sistema ({users.length})</h3>
                        </div>
                        
                        {users.length === 0 ? (
                            <div style={{padding: '40px', textAlign: 'center'}}>
                                <p style={{marginBottom: '20px', color: '#666'}}>No se encontraron usuarios en el sistema</p>
                                <button onClick={fetchUsers} className="btn-primary">
                                    Reintentar carga
                                </button>
                            </div>
                        ) : (
                            <table style={{
                                width: '100%',
                                borderCollapse: 'collapse',
                                background: 'white',
                                borderRadius: '8px',
                                overflow: 'hidden',
                                boxShadow: '0 1px 3px rgba(0,0,0,0.1)'
                            }}>
                                <thead>
                                    <tr style={{background: '#f7fafc'}}>
                                        <th style={{
                                            padding: '12px',
                                            textAlign: 'left',
                                            borderBottom: '1px solid #e2e8f0',
                                            fontWeight: '600'
                                        }}>ID</th>
                                        <th style={{
                                            padding: '12px',
                                            textAlign: 'left',
                                            borderBottom: '1px solid #e2e8f0',
                                            fontWeight: '600'
                                        }}>Nombre</th>
                                        <th style={{
                                            padding: '12px',
                                            textAlign: 'left',
                                            borderBottom: '1px solid #e2e8f0',
                                            fontWeight: '600'
                                        }}>Email</th>
                                        <th style={{
                                            padding: '12px',
                                            textAlign: 'left',
                                            borderBottom: '1px solid #e2e8f0',
                                            fontWeight: '600'
                                        }}>Rol</th>
                                        <th style={{
                                            padding: '12px',
                                            textAlign: 'left',
                                            borderBottom: '1px solid #e2e8f0',
                                            fontWeight: '600'
                                        }}>Fecha Creación</th>
                                        <th style={{
                                            padding: '12px',
                                            textAlign: 'left',
                                            borderBottom: '1px solid #e2e8f0',
                                            fontWeight: '600',
                                            width: '150px'
                                        }}>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {users.map(user => (
                                        <tr key={user.id} style={{borderBottom: '1px solid #e2e8f0'}}>
                                            <td style={{
                                                padding: '12px',
                                                fontFamily: 'monospace',
                                                fontSize: '12px'
                                            }}>{user.id}</td>
                                            <td style={{padding: '12px'}}>{user.name}</td>
                                            <td style={{padding: '12px'}}>{user.email}</td>
                                            <td style={{padding: '12px'}}>
                                                <span className={`role-badge ${user.role?.name}`} style={{
                                                    padding: '4px 8px',
                                                    borderRadius: '4px',
                                                    fontSize: '12px',
                                                    fontWeight: '600',
                                                    background: user.role?.name === 'admin' ? '#fed7d7' : 
                                                                user.role?.name === 'monitor' ? '#dbeafe' : '#c6f6d5',
                                                    color: user.role?.name === 'admin' ? '#c53030' : 
                                                        user.role?.name === 'monitor' ? '#1e40af' : '#276749'
                                                }}>
                                                    {user.role?.description || user.role?.name}
                                                </span>
                                            </td>
                                            <td style={{padding: '12px'}}>
                                                {new Date(user.created_at).toLocaleDateString('es-ES')}
                                            </td>
                                            <td style={{padding: '12px'}}>
                                                <div style={{display: 'flex', gap: '8px', flexWrap: 'wrap'}}>
                                                    <button 
                                                        onClick={() => handleEdit(user)}
                                                        className="btn-secondary"
                                                        style={{
                                                            padding: '6px 12px',
                                                            fontSize: '12px',
                                                            backgroundColor: '#4299e1',
                                                            color: 'white',
                                                            border: 'none',
                                                            borderRadius: '4px',
                                                            cursor: 'pointer'
                                                        }}
                                                    >
                                                        Editar
                                                    </button>
                                                    <button 
                                                        onClick={() => handleDelete(user.id)}
                                                        className="btn-danger"
                                                        style={{
                                                            padding: '6px 12px',
                                                            fontSize: '12px',
                                                            backgroundColor: '#e53e3e',
                                                            color: 'white',
                                                            border: 'none',
                                                            borderRadius: '4px',
                                                            cursor: 'pointer'
                                                        }}
                                                        disabled={user.id === JSON.parse(localStorage.getItem('user'))?.id}
                                                        title={user.id === JSON.parse(localStorage.getItem('user'))?.id ? "No puedes eliminar tu propio usuario" : ""}
                                                    >
                                                        Eliminar
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        )}
                    </div>
                </>
            )}
        </div>
    );
};

export default AdminPanel;