import React from 'react';

const TecnicoPanel = () => {
    return (
        <div className="tecnico-panel">
            <h2>Panel de Técnico</h2>
            <div className="tecnico-content">
                <div className="card">
                    <h3>Bienvenido al Sistema</h3>
                    <p>Como técnico, tienes acceso a las funciones básicas del sistema.</p>
                    <p>Puedes ver tu información y realizar las tareas asignadas.</p>
                </div>
                
                <div className="card">
                    <h3>Funciones Disponibles</h3>
                    <ul>
                        <li>Ver perfil de usuario</li>
                        <li>Consultar información del sistema</li>
                        <li>Realizar tareas técnicas asignadas</li>
                    </ul>
                </div>
            </div>
        </div>
    );
};

export default TecnicoPanel;