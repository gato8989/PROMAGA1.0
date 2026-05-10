import React, { useState, useEffect } from 'react';
import axios from 'axios';
import DatePicker from 'react-datepicker';
import {
  Chart as ChartJS,
  CategoryScale,
  LinearScale,
  PointElement,
  LineElement,
  BarElement,
  ArcElement,
  Title,
  Tooltip,
  Legend,
  Filler
} from 'chart.js';
import { Line, Bar, Pie } from 'react-chartjs-2';
import 'react-datepicker/dist/react-datepicker.css';

// Registrar componentes de Chart.js
ChartJS.register(
  CategoryScale,
  LinearScale,
  PointElement,
  LineElement,
  BarElement,
  ArcElement,
  Title,
  Tooltip,
  Legend,
  Filler
);

const DashboardPanel = () => {
  // Opciones de tiempo predefinidas para el dropdown
  const timeOptions = [
    { label: '📅 Últimos 7 días', days: 7, value: '7d' },
    { label: '📅 Últimos 14 días', days: 14, value: '14d' },
    { label: '📅 Último mes', days: 30, value: '1m' },
    { label: '📅 Últimos 2 meses', days: 60, value: '2m' },
    { label: '📅 Últimos 3 meses', days: 90, value: '3m' },
    { label: '📅 Últimos 6 meses', days: 180, value: '6m' },
    { label: '📅 Último año', days: 365, value: '1y' },
    { label: '📅 Últimos 2 años', days: 730, value: '2y' },
    { label: '📅 Últimos 5 años', days: 1825, value: '5y' },
    { label: '📅 Últimos 10 años', days: 3650, value: '10y' },
    { label: '🎯 Rango personalizado', days: null, value: 'custom' }
  ];

  const [selectedTimeOption, setSelectedTimeOption] = useState('14d');
  const [dateRange, setDateRange] = useState({
    start: new Date(new Date().setDate(new Date().getDate() - 14)),
    end: new Date()
  });
  const [showCustomDatePicker, setShowCustomDatePicker] = useState(false);

  const [stats, setStats] = useState(null);
  const [trend, setTrend] = useState(null);
  const [brands, setBrands] = useState(null);
  const [models, setModels] = useState(null);
  const [years, setYears] = useState(null);
  const [commonWorks, setCommonWorks] = useState(null);
  const [workTimes, setWorkTimes] = useState(null);
  const [hoursByBrand, setHoursByBrand] = useState(null);
  const [technicianPerformance, setTechnicianPerformance] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [noDataMessage, setNoDataMessage] = useState(null);

  const formatDate = (date) => date.toISOString().split('T')[0];

  const updateDateRange = (optionValue) => {
    const option = timeOptions.find(opt => opt.value === optionValue);
    
    if (option && option.value !== 'custom') {
      const startDate = new Date();
      startDate.setDate(startDate.getDate() - option.days);
      setDateRange({
        start: startDate,
        end: new Date()
      });
      setSelectedTimeOption(optionValue);
      setShowCustomDatePicker(false);
    } else if (optionValue === 'custom') {
      setSelectedTimeOption('custom');
      setShowCustomDatePicker(true);
    }
  };

  const handleCustomDateChange = (start, end) => {
    setDateRange({ start, end });
  };

  const applyCustomRange = () => {
    if (dateRange.start && dateRange.end) {
      setShowCustomDatePicker(false);
    }
  };

  const cancelCustomRange = () => {
    const lastNonCustom = '14d';
    updateDateRange(lastNonCustom);
    setShowCustomDatePicker(false);
  };

  const fetchAllData = async () => {
    try {
      setLoading(true);
      setError(null);
      setNoDataMessage(null);

      const params = {
        fecha_inicio: formatDate(dateRange.start),
        fecha_fin: formatDate(dateRange.end)
      };

      const queryString = new URLSearchParams(params).toString();

      console.log('Fetching dashboard data with params:', params);

      const [
        statsRes,
        trendRes,
        brandsRes,
        modelsRes,
        yearsRes,
        worksRes,
        timesRes,
        hoursRes,
        technicianRes
      ] = await Promise.all([
        axios.get(`/api/dashboard/stats?${queryString}`),
        axios.get(`/api/dashboard/trend?${queryString}`),
        axios.get(`/api/dashboard/brands?${queryString}`),
        axios.get(`/api/dashboard/models?${queryString}`),
        axios.get(`/api/dashboard/years?${queryString}`),
        axios.get(`/api/dashboard/common-works?${queryString}`),
        axios.get(`/api/dashboard/work-times?${queryString}`),
        axios.get(`/api/dashboard/hours-by-brand?${queryString}`),
        axios.get(`/api/dashboard/technician-performance?${queryString}`)
      ]);

      console.log('API Responses:', {
        stats: statsRes.data,
        trend: trendRes.data,
        brands: brandsRes.data,
        models: modelsRes.data,
        years: yearsRes.data,
        commonWorks: worksRes.data,
        workTimes: timesRes.data,
        hoursByBrand: hoursRes.data,
        technicianPerformance: technicianRes.data
      });

      const hasStatsData = statsRes.data.data && statsRes.data.data.totalVehiculos > 0;
      const hasTrendData = trendRes.data.data && trendRes.data.data.length > 0;
      const hasBrandsData = brandsRes.data.data && brandsRes.data.data.length > 0;
      
      if (!hasStatsData && !hasTrendData && !hasBrandsData) {
        setNoDataMessage('No hay datos disponibles para el período seleccionado. Por favor, selecciona otro rango de fechas.');
      }

      setStats(statsRes.data.data);
      setTrend(trendRes.data.data);
      setBrands(brandsRes.data.data);
      setModels(modelsRes.data.data);
      
      // Ordenar años de manera ascendente para la gráfica
      const yearsData = yearsRes.data.data || [];
      const sortedYears = [...yearsData].sort((a, b) => {
        const yearA = parseInt(a.año);
        const yearB = parseInt(b.año);
        return yearA - yearB;
      });
      setYears(sortedYears);
      
      // Ordenar trabajos comunes de mayor a menor
      const worksData = worksRes.data.data || [];
      const sortedWorks = [...worksData].sort((a, b) => b.cantidad - a.cantidad);
      setCommonWorks(sortedWorks);
      
      // Datos de tiempos de trabajo (ya vienen en horas desde el backend)
      setWorkTimes(timesRes.data.data || []);
      
      // Ordenar horas por marca de mayor a menor
      const hoursData = hoursRes.data.data || [];
      const sortedHours = [...hoursData].sort((a, b) => b.horas - a.horas);
      setHoursByBrand(sortedHours);
      
      // Datos de rendimiento de técnicos
      setTechnicianPerformance(technicianRes.data.data || []);
      
    } catch (err) {
      console.error('Error cargando datos del dashboard:', err);
      console.error('Error response:', err.response);
      setError(`Error al cargar los datos: ${err.response?.data?.error || err.message}`);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchAllData();
  }, [dateRange]);

  // Preparar datos para gráficas
  const brandChartData = {
    labels: brands?.map(b => b.marca) || ['Sin datos'],
    datasets: [{
      label: 'Vehículos por Marca',
      data: brands?.map(b => b.porcentaje) || [0],
      backgroundColor: [
        '#4155D4', '#7168A6', '#A07A78', '#D08D4A', '#FF9F1C',
        '#9C3685', '#CA275D', '#F71735', '#7C172E', '#011627'
      ],
      borderColor: '#fff',
      borderWidth: 2
    }]
  };

  const modelChartData = {
    labels: models?.map(m => m.modelo) || ['Sin datos'],
    datasets: [{
      label: 'Cantidad de Trabajos',
      data: models?.map(m => m.cantidad) || [0],
      backgroundColor: 'rgba(52, 152, 219, 0.7)',
      borderColor: '#261472',
      borderWidth: 1
    }]
  };

  // Gráfica de distribución de años (AREA CHART)
  const yearsChartData = {
    labels: years?.map(y => y.año) || [],
    datasets: [
      {
        label: 'Cantidad de Vehículos por Año',
        data: years?.map(y => y.cantidad) || [],
        borderColor: '#261472',
        backgroundColor: 'rgba(52, 152, 219, 0.7)',
        borderWidth: 3,
        fill: true,
        tension: 0.4,
        pointBackgroundColor: '#2980b9',
        pointBorderColor: '#fff',
        pointBorderWidth: 2,
        pointRadius: 4,
        pointHoverRadius: 6,
        pointHoverBackgroundColor: 'rgba(52, 152, 219, 0.9)',
      }
    ]
  };

  const yearsChartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
      legend: {
        position: 'top',
        labels: {
          font: { size: 12, weight: 'bold' },
          usePointStyle: true,
          boxWidth: 8
        }
      },
      tooltip: {
        callbacks: {
          label: function(context) {
            return `Vehículos: ${context.parsed.y}`;
          },
          title: function(context) {
            return `Año: ${context[0].label}`;
          }
        },
        backgroundColor: 'rgba(0,0,0,0.8)',
        titleColor: '#fff',
        bodyColor: '#fff',
        borderColor: '#261472',
        borderWidth: 2
      }
    },
    scales: {
      x: {
        title: {
          display: true,
          text: 'Año del Vehículo',
          font: { size: 14, weight: 'bold' },
          color: '#2c3e50'
        },
        ticks: {
          stepSize: 1,
          autoSkip: true,
          maxRotation: 45,
          minRotation: 45,
          font: { size: 11 }
        },
        grid: { display: true, color: 'rgba(0,0,0,0.05)' }
      },
      y: {
        title: {
          display: true,
          text: 'Cantidad de Vehículos',
          font: { size: 14, weight: 'bold' },
          color: '#2c3e50'
        },
        beginAtZero: true,
        ticks: {
          stepSize: 1,
          precision: 0,
          font: { size: 11 }
        },
        grid: { display: true, color: 'rgba(0,0,0,0.05)' }
      }
    },
    elements: {
      line: { borderJoin: 'round', borderCap: 'round' },
      point: { hoverBorderWidth: 3 }
    },
    interaction: { mode: 'index', intersect: false },
    hover: { mode: 'nearest', intersect: true }
  };

  // Gráfica de Tendencia (LÍNEA DOBLE - Sin relleno y menos curvatura)
  const trendChartData = {
    labels: trend?.map(t => {
        // FORZAR la fecha sin conversión de zona horaria
        const [year, month, day] = t.fecha.split('-');
        // Crear fecha en UTC para evitar desplazamiento
        const date = new Date(Date.UTC(year, month - 1, day));
        return date.toLocaleDateString('es-ES', { day: '2-digit', month: '2-digit', timeZone: 'UTC' });
    }) || [],
    datasets: [
        {
            label: 'Vehículos',
            data: trend?.map(t => t.vehiculos) || [],
            borderColor: '#261472',
            backgroundColor: 'transparent',
            borderWidth: 3,
            tension: 0.1,
            fill: false,
            pointBackgroundColor: '#261472',
            pointBorderColor: '#fff',
            pointBorderWidth: 2,
            pointRadius: 4,
            pointHoverRadius: 6,
            pointHoverBackgroundColor: 'rgba(52, 152, 219, 0.9)',
            yAxisID: 'y',
        },
        {
            label: 'Horas Trabajadas',
            data: trend?.map(t => t.horas) || [],
            borderColor: '#e74c3c',
            backgroundColor: 'transparent',
            borderWidth: 3,
            tension: 0.1,
            fill: false,
            pointBackgroundColor: '#c0392b',
            pointBorderColor: '#fff',
            pointBorderWidth: 2,
            pointRadius: 4,
            pointHoverRadius: 6,
            pointHoverBackgroundColor: '#e74c3c',
            yAxisID: 'y1',
        }
    ]
};

  const trendChartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    interaction: { 
      mode: 'index', 
      intersect: false 
    },
    plugins: {
      legend: {
        position: 'top',
        labels: {
          font: { size: 12, weight: 'bold' },
          usePointStyle: true,
          boxWidth: 10,
          padding: 15,
          generateLabels: function(chart) {
            const labels = ChartJS.defaults.plugins.legend.labels.generateLabels(chart);
            labels[0].text = 'Vehículos';
            labels[1].text = 'Horas Trabajadas';
            return labels;
          }
        }
      },
      tooltip: {
      callbacks: {
          label: function(context) {
              let label = context.dataset.label || '';
              if (label) {
                  label += ': ';
              }
              if (context.parsed.y !== null) {
                  if (context.dataset.label === 'Horas Trabajadas') {
                      label += context.parsed.y.toFixed(1) + ' horas';
                  } else {
                      label += context.parsed.y + ' vehículos';
                  }
              }
              return label;
          },
          title: function(context) {
              // Obtener la fecha original del trend
              const fechaOriginal = trend?.[context[0].dataIndex]?.fecha;
              if (fechaOriginal) {
                  // Parsear correctamente sin conversión de zona horaria
                  const [year, month, day] = fechaOriginal.split('-');
                  const date = new Date(Date.UTC(year, month - 1, day));
                  return date.toLocaleDateString('es-ES', { 
                      weekday: 'long', 
                      year: 'numeric', 
                      month: 'long', 
                      day: 'numeric',
                      timeZone: 'UTC'
                  });
              }
              return context[0].label;
          }
      },
        backgroundColor: 'rgba(0,0,0,0.8)',
        titleColor: '#fff',
        bodyColor: '#fff',
        borderColor: '#261472',
        borderWidth: 2,
        padding: 10
      }
    },
    scales: {
      x: {
        title: {
          display: true,
          text: 'Fecha',
          font: { size: 14, weight: 'bold', family: 'Arial' },
          color: '#2c3e50',
          padding: { top: 10 }
        },
        ticks: {
          autoSkip: true,
          maxRotation: 45,
          minRotation: 45,
          font: { size: 11, weight: '500' },
          color: '#34495e'
        },
        grid: {
          display: true,
          color: 'rgba(0,0,0,0.05)',
          drawBorder: true
        }
      },
      y: {
        type: 'linear',
        display: true,
        position: 'left',
        title: {
          display: true,
          text: 'Número de Vehículos',
          font: { size: 14, weight: 'bold', family: 'Arial' },
          color: '#3498db',
          padding: { bottom: 10 }
        },
        beginAtZero: true,
        ticks: {
          stepSize: 1,
          precision: 0,
          font: { size: 11, weight: '500' },
          color: '#3498db',
          callback: function(value) {
            return value + ' vehículos';
          }
        },
        grid: {
          display: true,
          color: 'rgba(52, 152, 219, 0.2)',
          drawBorder: true
        }
      },
      y1: {
        type: 'linear',
        display: true,
        position: 'right',
        title: {
          display: true,
          text: 'Horas Trabajadas',
          font: { size: 14, weight: 'bold', family: 'Arial' },
          color: '#e74c3c',
          padding: { bottom: 10 }
        },
        beginAtZero: true,
        ticks: {
          stepSize: 5,
          font: { size: 11, weight: '500' },
          color: '#e74c3c',
          callback: function(value) {
            return value.toFixed(1) + ' hrs';
          }
        },
        grid: {
          display: false,
          drawBorder: true
        }
      }
    },
    elements: {
      line: {
        borderJoin: 'round',
        borderCap: 'round'
      },
      point: {
        hoverBorderWidth: 3
      }
    },
    layout: {
      padding: {
        left: 10,
        right: 10,
        top: 20,
        bottom: 20
      }
    },
    animation: {
      duration: 1000,
      easing: 'easeOutQuart'
    }
  };

  // Gráfica de Trabajos Más Comunes (BARRAS verticales)
  const commonWorksChartData = {
    labels: commonWorks?.map(w => {
      let label = w.trabajo;
      if (label.length > 25) {
        label = label.substring(0, 22) + '...';
      }
      return label;
    }) || ['Sin datos'],
    datasets: [
      {
        label: 'Cantidad de Veces Realizado',
        data: commonWorks?.map(w => w.cantidad) || [0],
        backgroundColor: 'rgba(52, 152, 219, 0.7)',
        borderColor: '#261472',
        borderWidth: 2,
        borderRadius: 8,
        barPercentage: 0.7,
        categoryPercentage: 0.8,
        hoverBackgroundColor: 'rgba(52, 152, 219, 0.9)',
        hoverBorderColor: '#2980b9',
      }
    ]
  };

  const commonWorksChartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
      legend: {
        position: 'top',
        labels: {
          font: { size: 12, weight: 'bold' },
          usePointStyle: true,
          boxWidth: 8
        }
      },
      tooltip: {
        callbacks: {
          label: function(context) {
            return `Cantidad: ${context.parsed.y} veces`;
          },
          title: function(context) {
            return `Trabajo: ${commonWorks?.[context[0].dataIndex]?.trabajo || context[0].label}`;
          }
        },
        backgroundColor: 'rgba(0,0,0,0.8)',
        titleColor: '#fff',
        bodyColor: '#fff',
        borderColor: 'rgba(38, 20, 114, 0.8)',
        borderWidth: 2,
        padding: 10
      }
    },
    scales: {
      x: {
        title: {
          display: true,
          text: 'Tipo de Trabajo Realizado',
          font: { size: 14, weight: 'bold' },
          color: '#2c3e50'
        },
        ticks: {
          autoSkip: true,
          maxRotation: 45,
          minRotation: 45,
          font: { size: 10 }
        },
        grid: { display: false }
      },
      y: {
        title: {
          display: true,
          text: 'Número de Veces Realizado',
          font: { size: 14, weight: 'bold' },
          color: '#2c3e50'
        },
        beginAtZero: true,
        ticks: {
          stepSize: 1,
          precision: 0,
          font: { size: 11 },
          callback: function(value) {
            return value + ' veces';
          }
        },
        grid: { display: true, color: 'rgba(0,0,0,0.05)' }
      }
    },
    elements: {
      bar: {
        borderRadius: 8,
        borderSkipped: false,
      }
    },
    interaction: { mode: 'index', intersect: false },
    hover: { mode: 'nearest', intersect: true },
    animation: {
      duration: 1000,
      easing: 'easeOutQuart'
    }
  };

  // Gráfica de Tiempos de Competición (en horas)
  const workTimeChartData = {
    labels: workTimes?.map(w => {
      let label = w.trabajo;
      if (label.length > 20) {
        label = label.substring(0, 18) + '...';
      }
      return label;
    }) || ['Sin datos'],
    datasets: [
      {
        label: 'Mínimo (horas)',
        data: workTimes?.map(w => w.minimo) || [0],
        backgroundColor: 'rgba(144, 238, 144, 0.7)',
        borderColor: '#2E7D32',
        borderWidth: 1,
        borderRadius: 4,
      },
      {
        label: 'Promedio (horas)',
        data: workTimes?.map(w => w.promedio) || [0],
        backgroundColor: 'rgba(52, 152, 219, 0.7)',
        borderColor: '#261472',
        borderWidth: 1,
        borderRadius: 4,
      },
      {
        label: 'Máximo (horas)',
        data: workTimes?.map(w => w.maximo) || [0],
        backgroundColor: 'rgba(231, 76, 60, 0.7)',
        borderColor: '#C41E3A',
        borderWidth: 1,
        borderRadius: 4,
      }
    ]
  };

  const workTimeChartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
      legend: {
        position: 'top',
        labels: {
          font: { size: 12, weight: 'bold' },
          usePointStyle: true,
          boxWidth: 10,
          generateLabels: function(chart) {
            const labels = ChartJS.defaults.plugins.legend.labels.generateLabels(chart);
            labels[0].text = 'Mínimo';
            labels[1].text = 'Promedio';
            labels[2].text = 'Máximo';
            return labels;
          }
        }
      },
      tooltip: {
        callbacks: {
          label: function(context) {
            let label = context.dataset.label || '';
            if (label) {
              label += ': ';
            }
            if (context.parsed.y !== null) {
              label += context.parsed.y.toFixed(2) + ' horas';
            }
            return label;
          },
          title: function(context) {
            return `Trabajo: ${workTimes?.[context[0].dataIndex]?.trabajo || context[0].label}`;
          }
        },
        backgroundColor: 'rgba(0,0,0,0.8)',
        titleColor: '#fff',
        bodyColor: '#fff',
        borderColor: 'rgba(38, 20, 114, 0.8)',
        borderWidth: 2,
        padding: 10
      }
    },
    scales: {
      x: {
        title: {
          display: true,
          text: 'Tipo de Trabajo',
          font: { size: 14, weight: 'bold' },
          color: '#2c3e50'
        },
        ticks: {
          autoSkip: true,
          maxRotation: 35,
          minRotation: 35,
          font: { size: 10 }
        },
        grid: { display: false }
      },
      y: {
        title: {
          display: true,
          text: 'Tiempo (horas)',
          font: { size: 14, weight: 'bold' },
          color: '#2c3e50'
        },
        beginAtZero: true,
        ticks: {
          stepSize: 0.5,
          font: { size: 11 },
          callback: function(value) {
            return value.toFixed(1) + ' hrs';
          }
        },
        grid: { display: true, color: 'rgba(0,0,0,0.05)' }
      }
    },
    elements: {
      bar: {
        borderRadius: 6,
        borderSkipped: false,
      }
    },
    interaction: { 
      mode: 'index', 
      intersect: false 
    },
    animation: {
      duration: 1000,
      easing: 'easeOutQuart'
    }
  };

  // Gráfica de Horas por Marca (BARRAS HORIZONTALES)
  const hoursByBrandChartData = {
    labels: hoursByBrand?.map(h => h.marca) || ['Sin datos'],
    datasets: [
      {
        label: 'Horas Trabajadas por Marca',
        data: hoursByBrand?.map(h => h.horas) || [0],
        backgroundColor: 'rgba(52, 152, 219, 0.7)',
        borderColor: 'rgba(38, 20, 114, 0.8)',
        borderWidth: 2,
        borderRadius: 8,
        barPercentage: 0.7,
        categoryPercentage: 0.8,
        hoverBackgroundColor: 'rgba(52, 152, 219, 0.9)',
        hoverBorderColor: 'rgba(38, 20, 114, 0.8)',
      }
    ]
  };

  const hoursByBrandChartOptions = {
    indexAxis: 'y',
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
      legend: {
        position: 'top',
        labels: {
          font: { size: 12, weight: 'bold' },
          usePointStyle: true,
          boxWidth: 8
        }
      },
      tooltip: {
        callbacks: {
          label: function(context) {
            return `Horas trabajadas: ${context.parsed.x.toFixed(1)} horas`;
          },
          title: function(context) {
            return `Marca: ${context[0].label}`;
          }
        },
        backgroundColor: 'rgba(0,0,0,0.8)',
        titleColor: '#fff',
        bodyColor: '#fff',
        borderColor: 'rgba(38, 20, 114, 0.8)',
        borderWidth: 2,
        padding: 10
      }
    },
    scales: {
      x: {
        title: {
          display: true,
          text: 'Horas Trabajadas',
          font: { size: 14, weight: 'bold' },
          color: '#2c3e50'
        },
        beginAtZero: true,
        ticks: {
          stepSize: 10,
          font: { size: 11 },
          callback: function(value) {
            return value + ' hrs';
          }
        },
        grid: { display: true, color: 'rgba(0,0,0,0.05)' }
      },
      y: {
        title: {
          display: true,
          text: 'Marca de Vehículo',
          font: { size: 14, weight: 'bold' },
          color: '#2c3e50'
        },
        ticks: {
          font: { size: 12 },
          autoSkip: false
        },
        grid: { display: false }
      }
    },
    elements: {
      bar: {
        borderRadius: 8,
        borderSkipped: false,
      }
    },
    interaction: { mode: 'index', intersect: false },
    hover: { mode: 'nearest', intersect: true },
    animation: {
      duration: 1000,
      easing: 'easeOutQuart'
    }
  };

  const getDateRangeText = () => {
    const startDate = dateRange.start.toLocaleDateString('es-ES');
    const endDate = dateRange.end.toLocaleDateString('es-ES');
    return `${startDate} - ${endDate}`;
  };

  // Convertir minutos a horas con formato adecuado
  const convertMinutesToHours = (minutes) => {
    if (!minutes || minutes === 0) return '0';
    const hours = minutes / 60;
    return hours.toFixed(1);
  };

  if (loading && !stats) {
    return (
      <div className="dash-loading">
        <div className="dash-spinner"></div>
        <p>Cargando datos del dashboard...</p>
      </div>
    );
  }

  if (error) {
    return (
      <div className="dash-error">
        <div className="dash-error-icon">❌</div>
        <h3>Error al cargar el dashboard</h3>
        <p>{error}</p>
        <button onClick={fetchAllData} className="dash-retry-button">
          🔄 Reintentar
        </button>
      </div>
    );
  }

  return (
    <div className="dash-panel">
      <h2 className="dash-title">Panel de Análisis</h2>

      {/* Selector de tiempo con dropdown */}
      <div className="dash-time-selector">
        <div className="dash-selector-container">
          <label className="dash-selector-label">
            Período de análisis:
          </label>
          <div className="dash-select-wrapper">
            <select
              className="dash-time-dropdown"
              value={selectedTimeOption}
              onChange={(e) => updateDateRange(e.target.value)}
            >
              {timeOptions.map((option) => (
                <option key={option.value} value={option.value}>
                  {option.label}
                </option>
              ))}
            </select>
            <span className="dash-select-arrow">▼</span>
          </div>
        </div>

        {/* Panel de rango personalizado */}
        {showCustomDatePicker && (
          <div className="dash-custom-panel">
            <div className="dash-custom-header">
              <span className="dash-custom-title">📅 Seleccionar rango personalizado</span>
            </div>
            <div className="dash-date-filter">
              <div className="dash-date-input-group">
                <label className="dash-label">Fecha desde:</label>
                <DatePicker
                  selected={dateRange.start}
                  onChange={(date) => handleCustomDateChange(date, dateRange.end)}
                  dateFormat="dd/MM/yyyy"
                  maxDate={new Date()}
                  className="dash-datepicker"
                  placeholderText="Seleccionar fecha inicio"
                />
              </div>
              <div className="dash-date-input-group">
                <label className="dash-label">Fecha hasta:</label>
                <DatePicker
                  selected={dateRange.end}
                  onChange={(date) => handleCustomDateChange(dateRange.start, date)}
                  dateFormat="dd/MM/yyyy"
                  maxDate={new Date()}
                  minDate={dateRange.start}
                  className="dash-datepicker"
                  placeholderText="Seleccionar fecha fin"
                />
              </div>
              <div className="dash-custom-actions">
                <button onClick={applyCustomRange} className="dash-btn-apply">
                  ✓ Aplicar rango
                </button>
                <button onClick={cancelCustomRange} className="dash-btn-cancel">
                  ✗ Cancelar
                </button>
              </div>
            </div>
          </div>
        )}

        {/* Mostrar rango actual */}
        <div className="dash-current-range">
          <div className="dash-range-info">
            <span className="dash-range-icon">📆</span>
            <span className="dash-range-text">
              Rango actual: {getDateRangeText()}
            </span>
            {selectedTimeOption !== 'custom' && (
              <span className="dash-range-badge">
                {timeOptions.find(opt => opt.value === selectedTimeOption)?.label}
              </span>
            )}
          </div>
        </div>
      </div>

      {/* Mensaje de sin datos */}
      {noDataMessage && (
        <div className="dash-no-data-message">
          <div className="dash-info-icon">ℹ️</div>
          <p>{noDataMessage}</p>
        </div>
      )}

      {/* Tarjetas de resumen */}
      {stats && (
        <div className="dash-stats-cards">
          <div className="dash-stat-card dash-stat-card-1">
            <div className="dash-stat-icon">🚗</div>
            <div className="dash-stat-content">
              <h3 className="dash-stat-title">Total de Vehículos</h3>
              <p className="dash-stat-value">{stats.totalVehiculos || 0}</p>
            </div>
          </div>

          <div className="dash-stat-card dash-stat-card-2">
            <div className="dash-stat-icon">⏱️</div>
            <div className="dash-stat-content">
              <h3 className="dash-stat-title">Tiempo Promedio por Trabajo</h3>
              <p className="dash-stat-value">{convertMinutesToHours(stats.tiempoPromedio)} horas</p>
            </div>
          </div>

          <div className="dash-stat-card dash-stat-card-3">
            <div className="dash-stat-icon">🔧</div>
            <div className="dash-stat-content">
              <h3 className="dash-stat-title">Trabajos Realizados</h3>
              <p className="dash-stat-value">{stats.trabajosRealizados || 0}</p>
            </div>
          </div>
        </div>
      )}

      {/* Gráfica de Tendencia (Línea Doble) */}
      <div className="dash-chart-section">
        <h2 className="dash-section-title">Tendencia de Vehículos y Horas por Día</h2>
        <div className="dash-chart-container">
          {trend && trend.length > 0 ? (
            <Line data={trendChartData} options={trendChartOptions} />
          ) : (
            <div className="dash-empty-chart">
              <p>📊 No hay datos de tendencia para el período seleccionado</p>
              <p className="dash-hint">Intenta seleccionar un rango de fechas diferente</p>
            </div>
          )}
        </div>
        
        {/* Estadísticas adicionales de la tendencia */}
        {trend && trend.length > 0 && (
          <div className="dash-trend-stats">
            <div className="dash-trend-stat-item">
              <span className="dash-trend-stat-label">📊 Días analizados:</span>
              <span className="dash-trend-stat-value">{trend.length}</span>
            </div>
            <div className="dash-trend-stat-item">
              <span className="dash-trend-stat-label">🚗 Promedio vehículos/día:</span>
              <span className="dash-trend-stat-value">
                {(trend.reduce((sum, day) => sum + day.vehiculos, 0) / trend.length).toFixed(1)}
              </span>
            </div>
            <div className="dash-trend-stat-item">
              <span className="dash-trend-stat-label">⏱️ Promedio horas/día:</span>
              <span className="dash-trend-stat-value">
                {(trend.reduce((sum, day) => sum + day.horas, 0) / trend.length).toFixed(1)} hrs
              </span>
            </div>
            <div className="dash-trend-stat-item">
              <span className="dash-trend-stat-label">📈 Día con más vehículos:</span>
              <span className="dash-trend-stat-value">
                {trend.reduce((max, day) => day.vehiculos > max.vehiculos ? day : max, trend[0])?.vehiculos} vehículos
              </span>
            </div>
          </div>
        )}
      </div>

      {/* Gráfica de Marcas (Pastel) */}
      <div className="dash-chart-section">
        <h2 className="dash-section-title">Distribución de Marcas de Vehículos</h2>
        <div className="dash-chart-container dash-half-width">
          {brands && brands.length > 0 ? (
            <Pie
              data={brandChartData}
              options={{
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                  legend: { position: 'bottom' },
                  tooltip: {
                    callbacks: {
                      label: function (context) {
                        return context.label + ': ' + context.parsed + '%';
                      }
                    }
                  }
                }
              }}
            />
          ) : (
            <div className="dash-empty-chart">
              <p>🍩 No hay datos de marcas disponibles</p>
            </div>
          )}
        </div>
        {brands && brands.length > 0 && (
          <div className="dash-legend-table">
            <table className="dash-data-table">
              <thead>
                <tr>
                  <th>Marca</th>
                  <th>Cantidad</th>
                  <th>Porcentaje</th>
                </tr>
              </thead>
              <tbody>
                {brands.map((brand, idx) => (
                  <tr key={idx}>
                    <td>{brand.marca}</td>
                    <td>{brand.cantidad}</td>
                    <td>{brand.porcentaje}%</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>

      {/* Gráfica de Modelos (Barras Horizontal) */}
      <div className="dash-chart-section">
        <h2 className="dash-section-title">Modelos de Vehículos Más Trabajados</h2>
        <div className="dash-chart-container">
          {models && models.length > 0 ? (
            <Bar
              data={modelChartData}
              options={{
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: true } },
                scales: {
                  x: { beginAtZero: true }
                }
              }}
            />
          ) : (
            <div className="dash-empty-chart">
              <p>📊 No hay datos de modelos disponibles</p>
            </div>
          )}
        </div>
      </div>

      {/* Gráfica de Distribución de Años */}
      <div className="dash-chart-section">
        <h2 className="dash-section-title">Distribución de Años de Vehículos</h2>
        <div className="dash-chart-container">
          {years && years.length > 0 ? (
            <Line data={yearsChartData} options={yearsChartOptions} />
          ) : (
            <div className="dash-empty-chart">
              <p>📊 No hay datos de años disponibles para el período seleccionado</p>
              <p className="dash-hint">La gráfica mostrará la distribución por año cuando haya datos disponibles</p>
            </div>
          )}
        </div>
        
        {/* Estadísticas adicionales de años */}
        {years && years.length > 0 && (
          <div className="dash-years-stats">
            <div className="dash-years-summary">
              <div className="dash-years-stat-item">
                <span className="dash-years-stat-label">📊 Total de años distintos:</span>
                <span className="dash-years-stat-value">{years.length}</span>
              </div>
              <div className="dash-years-stat-item">
                <span className="dash-years-stat-label">📈 Año con más vehículos:</span>
                <span className="dash-years-stat-value">
                  {years.reduce((max, year) => year.cantidad > max.cantidad ? year : max, years[0])?.año}
                  {' '}
                  ({years.reduce((max, year) => year.cantidad > max.cantidad ? year : max, years[0])?.cantidad} vehículos)
                </span>
              </div>
              <div className="dash-years-stat-item">
                <span className="dash-years-stat-label">📉 Año con menos vehículos:</span>
                <span className="dash-years-stat-value">
                  {years.reduce((min, year) => year.cantidad < min.cantidad ? year : min, years[0])?.año}
                  {' '}
                  ({years.reduce((min, year) => year.cantidad < min.cantidad ? year : min, years[0])?.cantidad} vehículos)
                </span>
              </div>
            </div>
          </div>
        )}
      </div>

      {/* Gráfica de Trabajos Más Comunes */}
      <div className="dash-chart-section">
        <h2 className="dash-section-title">Trabajos Más Comunes Realizados</h2>
        <div className="dash-chart-container">
          {commonWorks && commonWorks.length > 0 ? (
            <Bar 
              data={commonWorksChartData} 
              options={commonWorksChartOptions}
            />
          ) : (
            <div className="dash-empty-chart">
              <p>🔧 No hay datos de trabajos disponibles para el período seleccionado</p>
              <p className="dash-hint">La gráfica mostrará los trabajos más realizados cuando haya datos disponibles</p>
            </div>
          )}
        </div>
        
        {/* Tabla resumen de trabajos */}
        {commonWorks && commonWorks.length > 0 && (
          <div className="dash-works-summary">
            <div className="dash-works-stats">
              <div className="dash-works-stat-item">
                <span className="dash-works-stat-label">📊 Total de trabajos distintos:</span>
                <span className="dash-works-stat-value">{commonWorks.length}</span>
              </div>
              <div className="dash-works-stat-item">
                <span className="dash-works-stat-label">🔝 Trabajo más realizado:</span>
                <span className="dash-works-stat-value">
                  {commonWorks[0]?.trabajo}
                  {' '}
                  ({commonWorks[0]?.cantidad} veces)
                </span>
              </div>
              <div className="dash-works-stat-item">
                <span className="dash-works-stat-label">📋 Total de trabajos ejecutados:</span>
                <span className="dash-works-stat-value">
                  {commonWorks.reduce((sum, work) => sum + work.cantidad, 0)} veces
                </span>
              </div>
            </div>
          </div>
        )}
      </div>

      {/* Gráfica de Tiempos de Competición (en horas) */}
      <div className="dash-chart-section">
        <h2 className="dash-section-title">Tiempos de Competición de Trabajos (Horas)</h2>
        <div className="dash-chart-container">
          {workTimes && workTimes.length > 0 ? (
            <Bar
              data={workTimeChartData}
              options={workTimeChartOptions}
            />
          ) : (
            <div className="dash-empty-chart">
              <p>⏱️ No hay datos de tiempos disponibles para el período seleccionado</p>
              <p className="dash-hint">Los trabajos con tiempo 0 horas también se mostrarán</p>
            </div>
          )}
        </div>
        
        {/* Estadísticas adicionales de tiempos */}
        {workTimes && workTimes.length > 0 && (
          <div className="dash-times-stats">
            <div className="dash-times-stat-item">
              <span className="dash-times-stat-label">📊 Total de trabajos analizados:</span>
              <span className="dash-times-stat-value">{workTimes.length}</span>
            </div>
            <div className="dash-times-stat-item">
              <span className="dash-times-stat-label">⚡ Trabajo más rápido (promedio):</span>
              <span className="dash-times-stat-value">
                {workTimes.reduce((min, w) => w.promedio < min.promedio ? w : min, workTimes[0])?.trabajo}
                {' '}
                ({workTimes.reduce((min, w) => w.promedio < min.promedio ? w : min, workTimes[0])?.promedio} hrs)
              </span>
            </div>
            <div className="dash-times-stat-item">
              <span className="dash-times-stat-label">🐢 Trabajo más lento (promedio):</span>
              <span className="dash-times-stat-value">
                {workTimes.reduce((max, w) => w.promedio > max.promedio ? w : max, workTimes[0])?.trabajo}
                {' '}
                ({workTimes.reduce((max, w) => w.promedio > max.promedio ? w : max, workTimes[0])?.promedio} hrs)
              </span>
            </div>
          </div>
        )}
      </div>

      {/* Gráfica de Horas por Marca (Barras Horizontales) */}
      <div className="dash-chart-section">
        <h2 className="dash-section-title">Horas de Trabajo por Marca de Vehículo</h2>
        <div className="dash-chart-container">
          {hoursByBrand && hoursByBrand.length > 0 ? (
            <Bar 
              data={hoursByBrandChartData} 
              options={hoursByBrandChartOptions}
            />
          ) : (
            <div className="dash-empty-chart">
              <p>⏱️ No hay datos de horas por marca disponibles para el período seleccionado</p>
              <p className="dash-hint">La gráfica mostrará las horas trabajadas por marca cuando haya datos disponibles</p>
            </div>
          )}
        </div>
        
        {/* Top 3 de marcas más trabajadas */}
        {hoursByBrand && hoursByBrand.length > 0 && (
          <div className="dash-hours-top3">
            <h3 className="dash-top3-title">Top 3 Marcas con Más Horas Trabajadas</h3>
            <div className="dash-top3-container">
              {hoursByBrand.slice(0, 3).map((item, index) => (
                <div key={index} className={`dash-top3-item dash-top3-item-${index + 1}`}>
                  <div className="dash-top3-rank">
                    {index === 0 && '🥇'}
                    {index === 1 && '🥈'}
                    {index === 2 && '🥉'}
                    <span className="dash-top3-number">#{index + 1}</span>
                  </div>
                  <div className="dash-top3-info">
                    <div className="dash-top3-brand">{item.marca}</div>
                    <div className="dash-top3-hours">
                      <span className="dash-hours-number">{item.horas.toFixed(1)}</span>
                      <span className="dash-hours-label"> horas</span>
                    </div>
                    <div className="dash-top3-bar-container">
                      <div 
                        className="dash-top3-bar" 
                        style={{ 
                          width: `${(item.horas / hoursByBrand[0].horas) * 100}%`,
                          backgroundColor: 'rgba(52, 152, 219, 0.7)'
                        }}
                      ></div>
                    </div>
                  </div>
                </div>
              ))}
            </div>
            
            {/* Estadísticas adicionales de horas */}
            <div className="dash-hours-stats">
              <div className="dash-hours-stat-item">
                <span className="dash-hours-stat-label">📊 Total de marcas:</span>
                <span className="dash-hours-stat-value">{hoursByBrand.length}</span>
              </div>
              <div className="dash-hours-stat-item">
                <span className="dash-hours-stat-label">⏱️ Total de horas trabajadas:</span>
                <span className="dash-hours-stat-value">
                  {hoursByBrand.reduce((sum, item) => sum + item.horas, 0).toFixed(1)} hrs
                </span>
              </div>
              <div className="dash-hours-stat-item">
                <span className="dash-hours-stat-label">📊 Promedio de horas por marca:</span>
                <span className="dash-hours-stat-value">
                  {(hoursByBrand.reduce((sum, item) => sum + item.horas, 0) / hoursByBrand.length).toFixed(1)} hrs
                </span>
              </div>
            </div>
          </div>
        )}
      </div>

      {/* Tabla de Rendimiento de Técnicos - Solo tabla, sin estadísticas adicionales */}
      <div className="dash-table-section">
          <h2 className="dash-section-title">Rendimiento de Técnicos</h2>
          {technicianPerformance && technicianPerformance.length > 0 ? (
              <div className="dash-technician-table-container">
                  <table className="dash-data-table dash-technician-table">
                      <thead>
                          <tr>
                              <th>Técnico</th>
                              <th>Vehiculos</th>
                              <th>Tiempo Promedio</th>
                              <th>Horas</th>
                              <th>Rendimiento</th>
                          </tr>
                      </thead>
                      <tbody>
                          {technicianPerformance.map((tecnico, idx) => (
                              <tr key={idx}>
                                  <td className="dash-technician-name">
                                      <span className="dash-technician-avatar">
                                          {tecnico.tecnico.charAt(0).toUpperCase()}
                                      </span>
                                      {tecnico.tecnico}
                                  </td>
                                  <td className="dash-technician-trabajos">{tecnico.trabajos}</td>
                                  <td className="dash-technician-tiempo">{tecnico.tiempo_promedio} hrs</td>
                                  <td className="dash-technician-horas">{tecnico.horas_trabajadas} hrs</td>
                                  <td>
                                      <span className={`dash-rendimiento-badge ${tecnico.rendimiento_class}`}>
                                          <span className="dash-rendimiento-icon">{tecnico.rendimiento_icon}</span>
                                          {tecnico.rendimiento}
                                      </span>
                                  </td>
                              </tr>
                          ))}
                      </tbody>
                  </table>
              </div>
          ) : (
              <div className="dash-empty-table">
                  <p>👨‍🔧 No hay datos de rendimiento de técnicos disponibles para el período seleccionado</p>
                  <p className="dash-hint">La tabla mostrará el rendimiento de los técnicos cuando haya datos disponibles</p>
              </div>
          )}
      </div>
    </div>
  );
};

export default DashboardPanel;