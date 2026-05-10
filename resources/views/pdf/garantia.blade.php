<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Certificado de Garantía</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Helvetica', Arial, sans-serif;
            color: #1a1a2e;
            line-height: 1.6;
            font-size: 12px;
        }
        
        .container {
            max-width: 700px;
            margin: 0 auto;
            padding: 30px;
        }
        
        .header {
            text-align: center;
            border-bottom: 3px solid #261472;
            padding-bottom: 15px;
            margin-bottom: 25px;
        }
        
        .header .logo {
            font-size: 22px;
            font-weight: 700;
            color: #261472;
            margin-bottom: 5px;
        }
        
        .header h1 {
            color: #261472;
            font-size: 24px;
            margin-bottom: 3px;
        }
        
        .header .subtitle {
            color: #666;
            font-size: 11px;
        }
        
        .numero-garantia {
            background: #261472;
            color: white;
            text-align: center;
            padding: 10px;
            border-radius: 6px;
            font-size: 14px;
            margin-bottom: 25px;
        }
        
        .section {
            margin-bottom: 20px;
        }
        
        .section h3 {
            color: #261472;
            font-size: 14px;
            border-bottom: 1px solid #e0e0e0;
            padding-bottom: 6px;
            margin-bottom: 10px;
        }
        
        .info-grid {
            display: table;
            width: 100%;
        }
        
        .info-row {
            display: table-row;
        }
        
        .info-item {
            display: table-cell;
            padding: 6px 0;
            width: 50%;
        }
        
        .info-item label {
            font-size: 10px;
            color: #888;
            display: block;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        
        .info-item span {
            font-size: 13px;
            font-weight: 500;
        }
        
        .trabajos-list {
            list-style: none;
            padding: 0;
        }
        
        .trabajos-list li {
            padding: 5px 0;
            border-bottom: 1px solid #f0f0f0;
            font-size: 12px;
        }
        
        .trabajos-list li::before {
            content: "-";
            color: #22c55e;
            margin-right: 6px;
            font-weight: bold;
        }
        
        .garantia-text {
            background: #f8f9ff;
            border-left: 4px solid #261472;
            padding: 12px;
            border-radius: 4px;
            font-size: 12px;
            margin-bottom: 20px;
        }
        
        .footer {
            text-align: center;
            font-size: 10px;
            color: #999;
            margin-top: 30px;
            padding-top: 15px;
            border-top: 1px solid #e0e0e0;
        }
        
        .firma-section {
            margin-top: 40px;
            text-align: center;
        }
        
        .firma-linea {
            width: 200px;
            border-top: 1px solid #333;
            margin: 30px auto 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="logo">AUTOMOTRIZ PROMAGA</div>
            <h1>CERTIFICADO DE GARANTÍA</h1>
            <p class="subtitle">Documento oficial de garantía de servicio</p>
        </div>

        <div class="numero-garantia">
            No. de Garantía: <strong>{{ $numero_garantia }}</strong>
        </div>

        <div class="section">
            <h3>-DATOS DEL CLIENTE</h3>
            <div class="info-grid">
                <div class="info-row">
                    <div class="info-item">
                        <label>Nombre</label>
                        <span>{{ $cliente->nombre }}</span>
                    </div>
                    <div class="info-item">
                        <label>Teléfono</label>
                        <span>{{ $cliente->telefono }}</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="section">
            <h3>-DATOS DEL VEHÍCULO</h3>
            <div class="info-grid">
                <div class="info-row">
                    <div class="info-item">
                        <label>Marca</label>
                        <span>{{ $trabajo->marca }}</span>
                    </div>
                    <div class="info-item">
                        <label>Modelo</label>
                        <span>{{ $trabajo->modelo }}</span>
                    </div>
                </div>
                <div class="info-row">
                    <div class="info-item">
                        <label>Año</label>
                        <span>{{ $trabajo->año }}</span>
                    </div>
                    <div class="info-item">
                        <label>Fecha de Servicio</label>
                        <span>{{ $trabajo->fecha_terminado }}</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="section">
            <h3>-TRABAJOS REALIZADOS</h3>
            <ul class="trabajos-list">
                @if($trabajo->subtrabajos_estado)
                    @php
                        $subtrabajos = is_array($trabajo->subtrabajos_estado) 
                            ? $trabajo->subtrabajos_estado 
                            : $trabajo->subtrabajos_estado->toArray();
                    @endphp
                    @foreach($subtrabajos as $nombre => $estado)
                        @if($estado)
                            <li>{{ $nombre }}</li>
                        @endif
                    @endforeach
                @endif
            </ul>
        </div>

        <div class="garantia-text">
            <strong>-TÉRMINOS DE GARANTÍA</strong><br><br>
            Por medio del presente, <strong>Automotriz ProMaga</strong> garantiza el servicio realizado al vehículo 
            <strong>{{ $vehiculo }}</strong> por un período de <strong>{{ $dias_garantia }} días</strong> 
            a partir del <strong>{{ $trabajo->fecha_terminado }}</strong>.<br><br>
            Esta garantía cubre exclusivamente la mano de obra de los trabajos realizados mencionados anteriormente.
            No incluye piezas o refacciones, las cuales están sujetas a la garantía del fabricante.<br><br>
            Para hacer válida esta garantía, presente este documento en nuestras instalaciones.
        </div>

        <div class="section">
            <h3>-VIGENCIA</h3>
            <div class="info-grid">
                <div class="info-row">
                    <div class="info-item">
                        <label>Fecha de Emisión</label>
                        <span>{{ $fecha_emision }}</span>
                    </div>
                    <div class="info-item">
                        <label>Vigencia</label>
                        <span>{{ $dias_garantia }} días</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="firma-section">
            <div class="firma-linea"></div>
            <p>Automotriz ProMaga</p>
        </div>

        <div class="footer">
            <p>Automotriz ProMaga - Todos los derechos reservados &copy; {{ date('Y') }}</p>
            <p>Este documento es generado electrónicamente y es válido sin firma autógrafa.</p>
        </div>
    </div>
</body>
</html>