const chartInstances = {};

const chartColors = {
    primary: '#339af0',
    secondary: '#000000',
    success: '#51cf66',
    warning: '#ff922b',
    danger: '#ff6b6b',
    info: '#4dabf7',
    light: '#e7f5ff',
    dark: '#212529'
};

const extendedColors = [
    '#339af0',
    '#51cf66',
    '#ff6b6b',
    '#ff922b',
    '#4dabf7',
    '#40c057',
    '#212529',
    '#adb5bd',
    '#37b24d',
    '#f03e3e',
    '#228be6',
    '#2b8a3e'
];

const severityColors = {
    'critical': '#ff6b6b',
    'high': '#ff922b',
    'medium': '#339af0',
    'low': '#51cf66'
};

function getSeverityColor(severity) {
    return severityColors[severity.toLowerCase()] || chartColors.info;
}

function generateColors(count) {
    const colors = [];
    for (let i = 0; i < count; i++) {
        colors.push(extendedColors[i % extendedColors.length]);
    }
    return colors;
}

function getDefaultOptions() {
    return {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom',
                labels: {
                    font: {
                        size: 12,
                        weight: '600',
                        family: "'Segoe UI', Tahoma, Geneva, Verdana, sans-serif"
                    },
                    color: '#000000',
                    padding: 15,
                    boxWidth: 15,
                    usePointStyle: true
                }
            },
            tooltip: {
                backgroundColor: '#000000',
                color: 'white',
                padding: 12,
                titleFont: { size: 14, weight: '600' },
                bodyFont: { size: 13 },
                borderColor: '#339af0',
                borderWidth: 2,
                displayColors: true,
                callbacks: {
                    labelColor: function(context) {
                        return {
                            borderColor: context.dataset.borderColor || context.dataset.backgroundColor,
                            backgroundColor: context.dataset.backgroundColor || context.dataset.borderColor,
                            borderWidth: 2,
                            borderRadius: 4
                        };
                    }
                }
            }
        }
    };
}

function getDefaultScaleOptions() {
    return {
        x: {
            grid: {
                color: 'rgba(0, 0, 0, 0.05)',
                drawBorder: true
            },
            ticks: {
                font: { size: 11 },
                color: '#666666'
            }
        },
        y: {
            beginAtZero: true,
            grid: {
                color: 'rgba(0, 0, 0, 0.05)',
                drawBorder: true
            },
            ticks: {
                font: { size: 11 },
                color: '#666666'
            }
        }
    };
}

function initializeChartDefaults() {
    Chart.defaults.font.family = "'Segoe UI', Tahoma, Geneva, Verdana, sans-serif";
    Chart.defaults.color = '#666666';
    Chart.defaults.borderColor = 'rgba(0, 0, 0, 0.1)';
}

function getCanvasContext(canvasId) {
    const canvas = document.getElementById(canvasId);
    if (!canvas) {
        console.error(`Canvas element with ID '${canvasId}' not found`);
        return null;
    }
    return canvas.getContext('2d');
}

function destroyChart(chartId) {
    if (chartInstances[chartId]) {
        chartInstances[chartId].destroy();
        delete chartInstances[chartId];
    }
}

function storeChartInstance(chartId, chartInstance) {
    destroyChart(chartId);
    chartInstances[chartId] = chartInstance;
}

function createBarChart(canvasId, labels, datasets, chartId = null, customOptions = {}) {
    const ctx = getCanvasContext(canvasId);
    if (!ctx) return null;

    if (!Array.isArray(datasets)) {
        datasets = [datasets];
    }

    datasets = datasets.map((dataset, index) => {
        return {
            label: dataset.label || `Dataset ${index + 1}`,
            data: dataset.data || [],
            backgroundColor: dataset.backgroundColor || extendedColors[index % extendedColors.length],
            borderColor: dataset.borderColor || 'white',
            borderWidth: dataset.borderWidth || 2,
            borderRadius: dataset.borderRadius || 6,
            ...dataset
        };
    });

    const options = {
        ...getDefaultOptions(),
        scales: getDefaultScaleOptions(),
        ...customOptions
    };

    const chart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: datasets
        },
        options: options
    });

    if (chartId) {
        storeChartInstance(chartId, chart);
    }

    return chart;
}

function createLineChart(canvasId, labels, datasets, chartId = null, customOptions = {}) {
    const ctx = getCanvasContext(canvasId);
    if (!ctx) return null;

    if (!Array.isArray(datasets)) {
        datasets = [datasets];
    }

    datasets = datasets.map((dataset, index) => {
        const color = dataset.backgroundColor || extendedColors[index % extendedColors.length];
        return {
            label: dataset.label || `Dataset ${index + 1}`,
            data: dataset.data || [],
            borderColor: dataset.borderColor || color,
            backgroundColor: dataset.backgroundColor || color,
            fill: dataset.fill !== undefined ? dataset.fill : true,
            tension: dataset.tension || 0.4,
            pointRadius: dataset.pointRadius || 4,
            pointBackgroundColor: dataset.pointBackgroundColor || color,
            pointBorderColor: 'white',
            pointBorderWidth: dataset.pointBorderWidth || 2,
            borderWidth: dataset.borderWidth || 3,
            ...dataset
        };
    });

    const options = {
        ...getDefaultOptions(),
        scales: getDefaultScaleOptions(),
        interaction: {
            mode: 'index',
            intersect: false
        },
        ...customOptions
    };

    const chart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: datasets
        },
        options: options
    });

    if (chartId) {
        storeChartInstance(chartId, chart);
    }

    return chart;
}

function createPieChart(canvasId, labels, data, chartId = null, customOptions = {}) {
    const ctx = getCanvasContext(canvasId);
    if (!ctx) return null;

    const options = {
        ...getDefaultOptions(),
        ...customOptions
    };

    const chart = new Chart(ctx, {
        type: 'pie',
        data: {
            labels: labels,
            datasets: [{
                data: data,
                backgroundColor: generateColors(data.length),
                borderColor: 'white',
                borderWidth: 2
            }]
        },
        options: options
    });

    if (chartId) {
        storeChartInstance(chartId, chart);
    }

    return chart;
}

function createDoughnutChart(canvasId, labels, data, chartId = null, customOptions = {}) {
    const ctx = getCanvasContext(canvasId);
    if (!ctx) return null;

    const options = {
        ...getDefaultOptions(),
        plugins: {
            ...getDefaultOptions().plugins,
            doughnut: {
                borderAlign: 'inner'
            }
        },
        ...customOptions
    };

    const chart = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: labels,
            datasets: [{
                data: data,
                backgroundColor: generateColors(data.length),
                borderColor: 'white',
                borderWidth: 2
            }]
        },
        options: options
    });

    if (chartId) {
        storeChartInstance(chartId, chart);
    }

    return chart;
}

function createRadarChart(canvasId, labels, datasets, chartId = null, customOptions = {}) {
    const ctx = getCanvasContext(canvasId);
    if (!ctx) return null;

    if (!Array.isArray(datasets)) {
        datasets = [datasets];
    }

    datasets = datasets.map((dataset, index) => {
        const color = dataset.backgroundColor || extendedColors[index % extendedColors.length];
        return {
            label: dataset.label || `Dataset ${index + 1}`,
            data: dataset.data || [],
            borderColor: dataset.borderColor || color,
            backgroundColor: dataset.backgroundColor || `${color}33`,
            pointBackgroundColor: color,
            pointBorderColor: 'white',
            pointBorderWidth: 2,
            tension: dataset.tension || 0.4,
            ...dataset
        };
    });

    const options = {
        ...getDefaultOptions(),
        scales: {
            r: {
                beginAtZero: true,
                grid: {
                    color: 'rgba(0, 0, 0, 0.05)'
                },
                ticks: {
                    font: { size: 11 }
                }
            }
        },
        ...customOptions
    };

    const chart = new Chart(ctx, {
        type: 'radar',
        data: {
            labels: labels,
            datasets: datasets
        },
        options: options
    });

    if (chartId) {
        storeChartInstance(chartId, chart);
    }

    return chart;
}

function createBubbleChart(canvasId, datasets, chartId = null, customOptions = {}) {
    const ctx = getCanvasContext(canvasId);
    if (!ctx) return null;

    if (!Array.isArray(datasets)) {
        datasets = [datasets];
    }

    datasets = datasets.map((dataset, index) => {
        return {
            label: dataset.label || `Dataset ${index + 1}`,
            data: dataset.data || [],
            backgroundColor: dataset.backgroundColor || extendedColors[index % extendedColors.length],
            borderColor: dataset.borderColor || 'white',
            borderWidth: dataset.borderWidth || 2,
            ...dataset
        };
    });

    const options = {
        ...getDefaultOptions(),
        scales: getDefaultScaleOptions(),
        ...customOptions
    };

    const chart = new Chart(ctx, {
        type: 'bubble',
        data: {
            datasets: datasets
        },
        options: options
    });

    if (chartId) {
        storeChartInstance(chartId, chart);
    }

    return chart;
}

function formatNumber(num) {
    return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
}

function formatPercentage(value, decimals = 1) {
    return (value * 100).toFixed(decimals) + '%';
}

function formatDuration(seconds) {
    const hours = Math.floor(seconds / 3600);
    const minutes = Math.floor((seconds % 3600) / 60);
    const secs = seconds % 60;

    let result = [];
    if (hours > 0) result.push(`${hours}h`);
    if (minutes > 0) result.push(`${minutes}m`);
    if (secs > 0 || result.length === 0) result.push(`${secs}s`);

    return result.join(' ');
}

function getSeverityColors(severities) {
    return severities.map(severity => getSeverityColor(severity));
}

function updateChartData(chartId, labels, data) {
    const chart = chartInstances[chartId];
    if (!chart) {
        console.error(`Chart with ID '${chartId}' not found`);
        return;
    }

    chart.data.labels = labels;
    chart.data.datasets[0].data = data;
    chart.update();
}

function addChartDataset(chartId, label, data) {
    const chart = chartInstances[chartId];
    if (!chart) {
        console.error(`Chart with ID '${chartId}' not found`);
        return;
    }

    const index = chart.data.datasets.length;
    chart.data.datasets.push({
        label: label,
        data: data,
        backgroundColor: extendedColors[index % extendedColors.length],
        borderColor: 'white',
        borderWidth: 2
    });

    chart.update();
}

function removeChartDataset(chartId, datasetIndex) {
    const chart = chartInstances[chartId];
    if (!chart) {
        console.error(`Chart with ID '${chartId}' not found`);
        return;
    }

    if (datasetIndex >= 0 && datasetIndex < chart.data.datasets.length) {
        chart.data.datasets.splice(datasetIndex, 1);
        chart.update();
    }
}

function toggleDatasetVisibility(chartId, datasetIndex) {
    const chart = chartInstances[chartId];
    if (!chart) {
        console.error(`Chart with ID '${chartId}' not found`);
        return;
    }

    const meta = chart.getDatasetMeta(datasetIndex);
    meta.hidden = !meta.hidden;
    chart.update();
}

function exportChartAsImage(chartId, filename = 'chart.png') {
    const chart = chartInstances[chartId];
    if (!chart) {
        console.error(`Chart with ID '${chartId}' not found`);
        return;
    }

    const url = chart.toBase64Image();
    const link = document.createElement('a');
    link.href = url;
    link.download = filename;
    link.click();
}

function exportChartAsCSV(chartId, filename = 'chart.csv') {
    const chart = chartInstances[chartId];
    if (!chart) {
        console.error(`Chart with ID '${chartId}' not found`);
        return;
    }

    let csv = '';
    const labels = chart.data.labels;
    const datasets = chart.data.datasets;

    csv += 'Label,' + datasets.map(ds => ds.label).join(',') + '\n';

    labels.forEach((label, index) => {
        csv += label + ',' + datasets.map(ds => ds.data[index] || 0).join(',') + '\n';
    });

    const blob = new Blob([csv], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = filename;
    link.click();
    window.URL.revokeObjectURL(url);
}

document.addEventListener('DOMContentLoaded', function() {
    initializeChartDefaults();
});

window.addEventListener('beforeunload', function() {
    Object.keys(chartInstances).forEach(chartId => {
        destroyChart(chartId);
    });
});